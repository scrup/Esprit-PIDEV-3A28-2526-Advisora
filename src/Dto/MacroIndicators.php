<?php

namespace App\Dto;

final class MacroIndicators
{
    public function __construct(
        private readonly string $countryCode,
        private readonly string $countryName,
        private readonly float $inflation,
        private readonly int $inflationYear,
        private readonly float $lendingRate,
        private readonly int $lendingRateYear,
        private readonly bool $lendingEstimated,
        private readonly float $gdpGrowth,
        private readonly int $gdpGrowthYear,
    ) {
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getCountryName(): string
    {
        return $this->countryName;
    }

    public function getInflation(): float
    {
        return $this->inflation;
    }

    public function getInflationYear(): int
    {
        return $this->inflationYear;
    }

    public function getLendingRate(): float
    {
        return $this->lendingRate;
    }

    public function getLendingRateYear(): int
    {
        return $this->lendingRateYear;
    }

    public function isLendingEstimated(): bool
    {
        return $this->lendingEstimated;
    }

    public function getGdpGrowth(): float
    {
        return $this->gdpGrowth;
    }

    public function getGdpGrowthYear(): int
    {
        return $this->gdpGrowthYear;
    }
}
