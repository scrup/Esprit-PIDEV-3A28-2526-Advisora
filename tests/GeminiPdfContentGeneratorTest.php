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

    public function testGenerateRetriesTransientGeminiErrorBeforeReturningAiContent(): void
    {
        $service = new class('test-api-key') extends GeminiPdfContentGenerator {
            public int $requestCount = 0;

            protected function sendJsonRequest(string $url, array $headers, array $payload): array
            {
                ++$this->requestCount;

                if ($this->requestCount === 1) {
                    return [
                        'status' => 503,
                        'body' => json_encode([
                            'error' => [
                                'message' => 'This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.',
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ];
                }

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'candidates' => [[
                            'finishReason' => 'STOP',
                            'content' => [
                                'parts' => [[
                                    'text' => json_encode([
                                        'executive_summary' => 'Synthese IA test.',
                                        'strategic_diagnosis' => 'Diagnostic IA test.',
                                        'highlights' => ['Point 1', 'Point 2', 'Point 3'],
                                        'strategic_priorities' => ['Priorite 1', 'Priorite 2', 'Priorite 3'],
                                        'opportunities' => ['Opportunite 1', 'Opportunite 2', 'Opportunite 3'],
                                        'expected_outcome_curve' => [
                                            ['period' => 'M1', 'value' => 12],
                                            ['period' => 'M3', 'value' => 34],
                                            ['period' => 'M6', 'value' => 68],
                                            ['period' => 'M12', 'value' => 120],
                                        ],
                                        'execution_phases' => [
                                            ['title' => 'Phase 1', 'horizon' => 'Debut', 'focus' => 'Focus 1'],
                                            ['title' => 'Phase 2', 'horizon' => 'Milieu', 'focus' => 'Focus 2'],
                                            ['title' => 'Phase 3', 'horizon' => 'Fin', 'focus' => 'Focus 3'],
                                        ],
                                        'risks' => ['Risque 1', 'Risque 2'],
                                        'mitigation_actions' => ['Action 1', 'Action 2', 'Action 3'],
                                        'actions' => ['Tache 1', 'Tache 2', 'Tache 3', 'Tache 4'],
                                        'kpis' => [
                                            ['name' => 'KPI 1', 'target' => '10', 'cadence' => 'Hebdomadaire'],
                                            ['name' => 'KPI 2', 'target' => '20', 'cadence' => 'Mensuelle'],
                                            ['name' => 'KPI 3', 'target' => '30', 'cadence' => 'Mensuelle'],
                                        ],
                                    ], JSON_THROW_ON_ERROR),
                                ]],
                            ],
                        ]],
                    ], JSON_THROW_ON_ERROR),
                ];
            }

            protected function pauseBeforeRetry(int $milliseconds): void
            {
            }
        };

        $content = $service->generate($this->buildStrategy(), null);
        $meta = $service->getLastGenerationMeta();

        self::assertTrue($meta['used_ai']);
        self::assertNull($meta['warning']);
        self::assertSame(2, $service->requestCount);
        self::assertSame('Synthese IA test.', $content['executive_summary']);
    }

    public function testGenerateUsesFriendlyFallbackWarningWhenGeminiIsTemporarilyUnavailable(): void
    {
        $service = new class('test-api-key') extends GeminiPdfContentGenerator {
            public int $requestCount = 0;

            protected function sendJsonRequest(string $url, array $headers, array $payload): array
            {
                ++$this->requestCount;

                return [
                    'status' => 503,
                    'body' => json_encode([
                        'error' => [
                            'message' => 'This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.',
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            }

            protected function pauseBeforeRetry(int $milliseconds): void
            {
            }
        };

        $content = $service->generate($this->buildStrategy(), null);
        $meta = $service->getLastGenerationMeta();

        self::assertFalse($meta['used_ai']);
        self::assertSame(3, $service->requestCount);
        self::assertStringContainsString('temporairement indisponible', (string) $meta['warning']);
        self::assertStringContainsString('playbook de secours', (string) $meta['warning']);
        self::assertStringNotContainsString('high demand', (string) $meta['warning']);
        self::assertArrayHasKey('executive_summary', $content);
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

    private function buildStrategy(): Strategie
    {
        return (new Strategie())
            ->setNomStrategie('Strategie test')
            ->setDureeTerme(12)
            ->setBudgetTotal(3000)
            ->setGainEstime(120);
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
