<?php

namespace App\DependencyInjection\Compiler;

use AhmedBhs\DoctrineDoctor\Analyzer\Integrity\NamingConventionAnalyzer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DisableDoctrineDoctorNamingConventionAnalyzerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(NamingConventionAnalyzer::class)) {
            return;
        }

        $container->getDefinition(NamingConventionAnalyzer::class)->clearTag('doctrine_doctor.analyzer');
    }
}
