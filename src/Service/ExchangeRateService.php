<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ExchangeRateService
{
    /**
     * @var list<string>
     */
    private const TARGET_CURRENCIES = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'CNY'];

    /**
     * @var array<string, int>
     */
    private const DECIMALS = [
        'JPY' => 2,
    ];

    private const SNAPSHOT_KEY = 'investment.exchange_rate.current.tnd.v1';
    private const PREVIOUS_SNAPSHOT_KEY = 'investment.exchange_rate.previous.tnd.v1';
    private const FALLBACK_TTL = 43200;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cachePool,
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return array{
     *     base: string,
     *     items: list<array{code: string, displayRate: string, changeLabel: string, changeClass: string}>,
     *     updatedAt: null|\DateTimeImmutable,
     *     usingFallback: bool,
     *     warning: null|string
     * }
     */
    public function getTickerData(string $baseCurrency = 'TND'): array
    {
        $snapshotItem = $this->cachePool->getItem(self::SNAPSHOT_KEY);
        $previousSnapshotItem = $this->cachePool->getItem(self::PREVIOUS_SNAPSHOT_KEY);

        $currentSnapshot = $snapshotItem->isHit() && is_array($snapshotItem->get()) ? $snapshotItem->get() : null;
        $previousSnapshot = $previousSnapshotItem->isHit() && is_array($previousSnapshotItem->get()) ? $previousSnapshotItem->get() : null;
        $usingFallback = false;
        $warning = null;

        if ($this->shouldRefreshSnapshot($currentSnapshot)) {
            try {
                $freshSnapshot = $this->fetchLatestSnapshot($baseCurrency);

                if (is_array($currentSnapshot) && ($currentSnapshot['updated_at_unix'] ?? 0) !== ($freshSnapshot['updated_at_unix'] ?? 0)) {
                    $previousSnapshotItem->set($currentSnapshot)->expiresAfter(604800);
                    $this->cachePool->save($previousSnapshotItem);
                    $previousSnapshot = $currentSnapshot;
                }

                $currentSnapshot = $freshSnapshot;
                $snapshotItem->set($freshSnapshot)->expiresAfter($this->computeSnapshotTtl($freshSnapshot));
                $this->cachePool->save($snapshotItem);
            } catch (\Throwable) {
                $usingFallback = is_array($currentSnapshot);
                $warning = $usingFallback ? 'Affichage des dernieres donnees disponibles.' : 'Ticker temporairement indisponible.';
            }
        }

        if (!is_array($currentSnapshot)) {
            return [
                'base' => $baseCurrency,
                'items' => [],
                'updatedAt' => null,
                'usingFallback' => false,
                'warning' => $warning,
            ];
        }

        $items = [];
        foreach (self::TARGET_CURRENCIES as $code) {
            $rate = $currentSnapshot['rates'][$code] ?? null;
            if (!is_numeric($rate)) {
                continue;
            }

            $previousRate = $previousSnapshot['rates'][$code] ?? null;
            $changeLabel = '0.00%';
            $changeClass = 'neutral';

            if (is_numeric($previousRate) && (float) $previousRate > 0) {
                $changePercent = (((float) $rate - (float) $previousRate) / (float) $previousRate) * 100;
                $changeLabel = sprintf('%+0.2f%%', $changePercent);
                $changeClass = $changePercent >= 0 ? 'positive' : 'negative';
            }

            $items[] = [
                'code' => $code,
                'displayRate' => number_format((float) $rate, self::DECIMALS[$code] ?? 4, '.', ''),
                'changeLabel' => $changeLabel,
                'changeClass' => $changeClass,
            ];
        }

        $updatedAt = null;
        if (isset($currentSnapshot['updated_at_unix']) && is_numeric($currentSnapshot['updated_at_unix'])) {
            $updatedAt = (new \DateTimeImmutable('@' . (int) $currentSnapshot['updated_at_unix']))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }

        return [
            'base' => (string) ($currentSnapshot['base'] ?? $baseCurrency),
            'items' => $items,
            'updatedAt' => $updatedAt,
            'usingFallback' => $usingFallback,
            'warning' => $warning,
        ];
    }

    private function shouldRefreshSnapshot(?array $snapshot): bool
    {
        if ($this->apiKey === '') {
            return false;
        }

        if ($snapshot === null) {
            return true;
        }

        $nextUpdateUnix = (int) ($snapshot['next_update_unix'] ?? 0);
        if ($nextUpdateUnix > 0) {
            return time() >= $nextUpdateUnix;
        }

        $fetchedAtUnix = (int) ($snapshot['fetched_at_unix'] ?? 0);

        return $fetchedAtUnix === 0 || (time() - $fetchedAtUnix) >= self::FALLBACK_TTL;
    }

    /**
     * @return array{
     *     base: string,
     *     rates: array<string, float>,
     *     updated_at_unix: int,
     *     next_update_unix: int,
     *     fetched_at_unix: int
     * }
     */
    private function fetchLatestSnapshot(string $baseCurrency): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('La cle ExchangeRate-API est manquante.');
        }

        $response = $this->httpClient->request('GET', sprintf(
            '%s/%s/latest/%s',
            rtrim($this->baseUrl, '/'),
            $this->apiKey,
            $baseCurrency
        ), [
            'timeout' => 15,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Erreur HTTP ExchangeRate-API %d.', $response->getStatusCode()));
        }

        $payload = $response->toArray(false);
        if (($payload['result'] ?? null) !== 'success') {
            throw new \RuntimeException(sprintf(
                'Reponse ExchangeRate-API invalide: %s.',
                (string) ($payload['error-type'] ?? 'unknown')
            ));
        }

        $conversionRates = $payload['conversion_rates'] ?? null;
        if (!is_array($conversionRates)) {
            throw new \RuntimeException('Les taux de conversion ExchangeRate-API sont absents.');
        }

        $filteredRates = [];
        foreach (self::TARGET_CURRENCIES as $currencyCode) {
            if (isset($conversionRates[$currencyCode]) && is_numeric($conversionRates[$currencyCode])) {
                $filteredRates[$currencyCode] = (float) $conversionRates[$currencyCode];
            }
        }

        return [
            'base' => (string) ($payload['base_code'] ?? $baseCurrency),
            'rates' => $filteredRates,
            'updated_at_unix' => (int) ($payload['time_last_update_unix'] ?? time()),
            'next_update_unix' => (int) ($payload['time_next_update_unix'] ?? (time() + self::FALLBACK_TTL)),
            'fetched_at_unix' => time(),
        ];
    }

    private function computeSnapshotTtl(array $snapshot): int
    {
        $nextUpdateUnix = (int) ($snapshot['next_update_unix'] ?? 0);
        if ($nextUpdateUnix <= time()) {
            return 900;
        }

        return max(900, $nextUpdateUnix - time());
    }
}
