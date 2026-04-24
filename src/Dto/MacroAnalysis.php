<?php

namespace App\Dto;

final class MacroAnalysis
{
    public const RISK_LOW = 'LOW';
    public const RISK_MEDIUM = 'MEDIUM';
    public const RISK_HIGH = 'HIGH';

    public function __construct(
        private readonly MacroIndicators $data,
        private readonly float $score,
        private readonly string $riskLevel,
        private readonly float $grossRoi,
        private readonly float $adjustedRoi,
        private readonly float $riskPremium,
    ) {
    }

    public function getData(): MacroIndicators
    {
        return $this->data;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getRiskLevel(): string
    {
        return $this->riskLevel;
    }

    public function getGrossRoi(): float
    {
        return $this->grossRoi;
    }

    public function getAdjustedRoi(): float
    {
        return $this->adjustedRoi;
    }

    public function getRiskPremium(): float
    {
        return $this->riskPremium;
    }

    public function getRiskLevelLabel(): string
    {
        return match ($this->riskLevel) {
            self::RISK_LOW => 'Risque faible',
            self::RISK_MEDIUM => 'Risque moyen',
            default => 'Risque eleve',
        };
    }

    public function getRiskLevelBadgeClass(): string
    {
        return match ($this->riskLevel) {
            self::RISK_LOW => 'low',
            self::RISK_MEDIUM => 'medium',
            default => 'high',
        };
    }
}
