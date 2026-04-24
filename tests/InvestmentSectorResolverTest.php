<?php

namespace App\Tests;

use App\Service\InvestmentSectorResolver;
use PHPUnit\Framework\TestCase;

final class InvestmentSectorResolverTest extends TestCase
{
    public function testItNormalizesKnownTechnologyAliases(): void
    {
        $resolver = new InvestmentSectorResolver();

        $profile = $resolver->resolve('IT / Technologie');

        self::assertSame('IT / Technologie', $profile['label']);
        self::assertContains('technologie', $profile['matching_types']);
    }

    public function testItFallsBackToTransportLogistiqueForTransportInputs(): void
    {
        $resolver = new InvestmentSectorResolver();

        $profile = $resolver->resolve('Transport');

        self::assertSame('Transport / Logistique', $profile['label']);
    }

    public function testItFallsBackToGenericSectorWhenUnknown(): void
    {
        $resolver = new InvestmentSectorResolver();

        $profile = $resolver->resolve('Biotech spatiale');

        self::assertSame('Autre / Multisectoriel', $profile['label']);
    }

    public function testItRecognizesLegacyProjectTypesUsedInTheDatabase(): void
    {
        $resolver = new InvestmentSectorResolver();

        self::assertSame('IT / Technologie', $resolver->resolve('Informatique')['label']);
        self::assertSame('IT / Technologie', $resolver->resolve('Developpement web')['label']);
        self::assertSame('Services / Business', $resolver->resolve('Commercial')['label']);
    }

    public function testItAvoidsShortAliasFalsePositives(): void
    {
        $resolver = new InvestmentSectorResolver();

        $profile = $resolver->resolve('Commercial');

        self::assertNotSame('IT / Technologie', $profile['label']);
    }
}
