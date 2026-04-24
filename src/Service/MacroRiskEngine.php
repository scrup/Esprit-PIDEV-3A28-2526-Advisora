<?php

namespace App\Service;

use App\Dto\MacroAnalysis;
use App\Dto\MacroIndicators;

final class MacroRiskEngine
{
    private const GROSS_ROI = 12.0;

    public function analyse(MacroIndicators $indicators): MacroAnalysis
    {
        $rawScore = ($indicators->getInflation() * 3.0)
            + ($indicators->getLendingRate() * 2.0)
            + (max(0.0, 3.0 - $indicators->getGdpGrowth()) * 5.0);

        $score = $this->clamp($rawScore, 0.0, 100.0);

        $riskLevel = match (true) {
            $score <= 33.0 => MacroAnalysis::RISK_LOW,
            $score <= 66.0 => MacroAnalysis::RISK_MEDIUM,
            default => MacroAnalysis::RISK_HIGH,
        };

        $riskPremium = match ($riskLevel) {
            MacroAnalysis::RISK_LOW => 1.5,
            MacroAnalysis::RISK_MEDIUM => 3.0,
            default => 5.0,
        };

        $adjustedRoi = self::GROSS_ROI - $indicators->getInflation() - $riskPremium;

        return new MacroAnalysis(
            $indicators,
            round($score, 2),
            $riskLevel,
            self::GROSS_ROI,
            round($adjustedRoi, 2),
            $riskPremium,
        );
    }

    private function clamp(float $value, float $min, float $max): float
    {
        if (!is_finite($value)) {
            return $min;
        }

        return max($min, min($max, $value));
    }
}
