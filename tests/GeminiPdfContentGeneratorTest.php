<?php

namespace App\Tests;

use App\Entity\Strategie;
use App\Service\GeminiPdfContentGenerator;
use PHPUnit\Framework\TestCase;

class GeminiPdfContentGeneratorTest extends TestCase
{
    public function testEnvironmentOverridesAreAppliedWhenConstructorUsesDefaults(): void
    {
        $this->withEnvironment([
            'GEMINI_API_KEY' => 'test-api-key',
            'GOOGLE_API_KEY' => null,
            'GEMINI_MODEL' => 'models/gemini-1.5-pro',
            'GEMINI_API_BASE_URL' => 'https://example.test/gemini',
        ], function (): void {
            $service = new GeminiPdfContentGenerator();

            self::assertSame('test-api-key', $this->readPrivateProperty($service, 'apiKey'));
            self::assertSame('gemini-1.5-pro', $this->readPrivateProperty($service, 'model'));
            self::assertSame('https://example.test/gemini', $this->readPrivateProperty($service, 'baseUrl'));
        });
    }

    public function testMissingApiKeyRecordsFallbackWarning(): void
    {
        $this->withEnvironment([
            'GEMINI_API_KEY' => null,
            'GOOGLE_API_KEY' => null,
            'GEMINI_MODEL' => null,
            'GEMINI_API_BASE_URL' => null,
        ], function (): void {
            $service = new GeminiPdfContentGenerator();
            $strategy = (new Strategie())
                ->setNomStrategie('Strategie test')
                ->setDureeTerme(6)
                ->setBudgetTotal(1200)
                ->setGainEstime(140);

            $content = $service->generate($strategy, null);
            $meta = $service->getLastGenerationMeta();

            self::assertFalse($meta['used_ai']);
            self::assertStringContainsString('GEMINI_API_KEY', (string) $meta['warning']);
            self::assertArrayHasKey('executive_summary', $content);
            self::assertArrayHasKey('expected_outcome_chart', $content);
            self::assertCount(5, $content['expected_outcome_chart']['points']);
            self::assertNotSame('', trim((string) $content['expected_outcome_summary']));
            self::assertNotSame('', trim((string) $content['executive_summary']));
        });
    }

    public function testNormalizeGeneratedContentBuildsChartFromExpectedOutcomeCurve(): void
    {
        $service = new GeminiPdfContentGenerator('test-api-key');
        $strategy = (new Strategie())
            ->setNomStrategie('Strategie croissance')
            ->setDureeTerme(12)
            ->setBudgetTotal(3000)
            ->setGainEstime(140);

        $content = $this->invokePrivateMethod($service, 'normalizeGeneratedContent', [[
            'expected_outcome_curve' => [
                ['period' => 'M1', 'value' => 10],
                ['period' => 'M3', 'value' => '35'],
                ['period' => 'M6', 'value' => 65],
                ['period' => 'M9', 'value' => 105],
                ['period' => 'M12', 'value' => 140],
            ],
        ], $strategy, null]);

        self::assertSame('140 %', $content['expected_outcome_chart']['final_value_label']);
        self::assertCount(5, $content['expected_outcome_chart']['points']);
        self::assertStringContainsString('M12', $content['expected_outcome_summary']);
        self::assertNotSame('', trim((string) $content['expected_outcome_chart']['polyline_points']));
    }

    private function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    /**
     * @param array<string, string|null> $variables
     */
    private function withEnvironment(array $variables, callable $callback): void
    {
        $originalValues = [];

        foreach ($variables as $name => $value) {
            $originalValues[$name] = [
                'getenv' => getenv($name),
                'env_exists' => array_key_exists($name, $_ENV),
                'env_value' => $_ENV[$name] ?? null,
                'server_exists' => array_key_exists($name, $_SERVER),
                'server_value' => $_SERVER[$name] ?? null,
            ];

            if ($value === null) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);

                continue;
            }

            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        try {
            $callback();
        } finally {
            foreach ($originalValues as $name => $snapshot) {
                if ($snapshot['getenv'] === false) {
                    putenv($name);
                } else {
                    putenv(sprintf('%s=%s', $name, $snapshot['getenv']));
                }

                if ($snapshot['env_exists']) {
                    $_ENV[$name] = $snapshot['env_value'];
                } else {
                    unset($_ENV[$name]);
                }

                if ($snapshot['server_exists']) {
                    $_SERVER[$name] = $snapshot['server_value'];
                } else {
                    unset($_SERVER[$name]);
                }
            }
        }
    }
}
