<?php

namespace App\Service;

use App\Dto\MacroIndicators;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WorldBankService
{
    private const CACHE_KEY = 'investment.world_bank.tunisia.v1';
    private const CACHE_TTL = 86400;
    private const INFLATION_INDICATOR = 'FP.CPI.TOTL.ZG';
    private const LENDING_INDICATOR = 'FR.INR.LEND';
    private const GDP_GROWTH_INDICATOR = 'NY.GDP.MKTP.KD.ZG';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cachePool,
        private readonly string $baseUrl,
        private readonly float $tunisiaLendingFallbackRate,
        private readonly int $tunisiaLendingFallbackYear,
    ) {
    }

    public function fetchTunisiaIndicators(): MacroIndicators
    {
        $cacheItem = $this->cachePool->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit()) {
            $cachedIndicators = $cacheItem->get();
            if ($cachedIndicators instanceof MacroIndicators) {
                return $cachedIndicators;
            }
        }

        $inflation = $this->fetchLatestIndicator(self::INFLATION_INDICATOR);
        $gdpGrowth = $this->fetchLatestIndicator(self::GDP_GROWTH_INDICATOR);
        $lendingRate = $this->fetchLatestLendingRate();

        $indicators = new MacroIndicators(
            'TN',
            'Tunisie',
            $inflation['value'],
            $inflation['year'],
            $lendingRate['value'],
            $lendingRate['year'],
            $lendingRate['estimated'],
            $gdpGrowth['value'],
            $gdpGrowth['year'],
        );

        $cacheItem->set($indicators)->expiresAfter(self::CACHE_TTL);
        $this->cachePool->save($cacheItem);

        return $indicators;
    }

    /**
     * @return array{value: float, year: int}
     */
    private function fetchLatestIndicator(string $indicator): array
    {
        $payload = $this->requestIndicator($indicator);
        $dataRows = $payload[1] ?? null;

        if (!is_array($dataRows) || $dataRows === []) {
            throw new \RuntimeException(sprintf('Aucune donnee World Bank disponible pour %s.', $indicator));
        }

        foreach ($dataRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $value = $row['value'] ?? null;
            $year = $row['date'] ?? null;
            if ($value === null || $year === null) {
                continue;
            }

            return [
                'value' => (float) $value,
                'year' => (int) $year,
            ];
        }

        throw new \RuntimeException(sprintf('Aucune valeur exploitable pour %s.', $indicator));
    }

    /**
     * @return array{value: float, year: int, estimated: bool}
     */
    private function fetchLatestLendingRate(): array
    {
        try {
            $indicator = $this->fetchLatestIndicator(self::LENDING_INDICATOR);

            return [
                'value' => $indicator['value'],
                'year' => $indicator['year'],
                'estimated' => false,
            ];
        } catch (\Throwable) {
            return [
                'value' => $this->tunisiaLendingFallbackRate,
                'year' => $this->tunisiaLendingFallbackYear,
                'estimated' => true,
            ];
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function requestIndicator(string $indicator): array
    {
        $url = sprintf(
            '%s/country/TN/indicator/%s',
            rtrim($this->baseUrl, '/'),
            $indicator
        );

        $response = $this->httpClient->request('GET', $url, [
            'query' => [
                'format' => 'json',
                'mrv' => 10,
                'per_page' => 10,
            ],
            'timeout' => 15,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Erreur HTTP World Bank %d sur %s.', $response->getStatusCode(), $indicator));
        }

        $payload = $response->toArray(false);
        if (!is_array($payload) || count($payload) < 2) {
            throw new \RuntimeException(sprintf('Structure World Bank inattendue pour %s.', $indicator));
        }

        return $payload;
    }
}
