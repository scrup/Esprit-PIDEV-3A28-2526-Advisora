<?php

namespace App\DependencyInjection\Compiler;

use AhmedBhs\DoctrineDoctor\Analyzer\Configuration\TimeZoneAnalyzer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DisableDoctrineDoctorTimeZoneAnalyzerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(TimeZoneAnalyzer::class)) {
            return;
        }

        $container->getDefinition(TimeZoneAnalyzer::class)->clearTag('doctrine_doctor.analyzer');
    }
}
