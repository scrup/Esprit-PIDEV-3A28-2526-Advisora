<?php

namespace App\Tests;

use App\Dto\MacroAnalysis;
use App\Dto\MacroIndicators;
use App\Service\MacroRiskEngine;
use PHPUnit\Framework\TestCase;

final class MacroRiskEngineTest extends TestCase
{
    public function testItComputesHighRiskWhenInflationAndRatesAreElevated(): void
    {
        $engine = new MacroRiskEngine();
        $indicators = new MacroIndicators('TN', 'Tunisie', 9.2, 2024, 8.0, 2024, true, 0.5, 2024);

        $analysis = $engine->analyse($indicators);

        self::assertSame(MacroAnalysis::RISK_HIGH, $analysis->getRiskLevel());
        self::assertGreaterThan(66.0, $analysis->getScore());
        self::assertLessThan(0.0, $analysis->getAdjustedRoi());
    }

    public function testItComputesLowRiskWhenMacroIndicatorsAreComfortable(): void
    {
        $engine = new MacroRiskEngine();
        $indicators = new MacroIndicators('TN', 'Tunisie', 2.3, 2024, 3.8, 2024, false, 5.5, 2024);

        $analysis = $engine->analyse($indicators);

        self::assertSame(MacroAnalysis::RISK_LOW, $analysis->getRiskLevel());
        self::assertLessThanOrEqual(33.0, $analysis->getScore());
        self::assertGreaterThan(0.0, $analysis->getAdjustedRoi());
    }
}
