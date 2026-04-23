<?php

namespace App\Twig;

use App\Service\ExchangeRateService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MarketDataExtension extends AbstractExtension
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('exchange_ticker_data', [$this, 'getExchangeTickerData']),
        ];
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
    public function getExchangeTickerData(): array
    {
        return $this->exchangeRateService->getTickerData();
    }
}
