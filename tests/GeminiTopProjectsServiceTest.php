<?php

namespace App\Tests;

use App\Service\GeminiTopProjectsService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GeminiTopProjectsServiceTest extends TestCase
{
    public function testGenerateTopProjectsParsesMarkdownWrappedJsonAndTracksLiveMeta(): void
    {
        $service = $this->createServiceFromText(<<<'TEXT'
```json
{
  "projects": [
    {
      "rank": 1,
      "name": "Amazon",
      "country": "United States",
      "annual_revenue": "574.8 milliards USD",
      "description": "Leader mondial du commerce numerique.",
      "founded": "1994",
      "why_top": "Execution logistique."
    },
    {
      "rank": 2,
      "name": "Alibaba",
      "country": "China",
      "annual_revenue": "126.5 milliards USD",
      "description": "Acteur cle du commerce electronique asiatique.",
      "founded": "1999",
      "why_top": "Puissance de plateforme."
    },
    {
      "rank": 3,
      "name": "Mercado Libre",
      "country": "Argentina",
      "annual_revenue": "14.5 milliards USD",
      "description": "Plateforme majeure en Amerique latine.",
      "founded": "1999",
      "why_top": "Position regionale dominante."
    }
  ]
}
```
TEXT);

        $projects = $service->generateTopProjects('E-commerce');
        $meta = $service->getLastGenerationMeta();

        self::assertCount(3, $projects);
        self::assertSame('top10-rank-gold', $projects[0]['border_class']);
        self::assertSame('🇺🇸', $projects[0]['country_flag']);
        self::assertSame('live', $meta['source']);
        self::assertSame('gemini-2.5-flash', $meta['model']);
        self::assertFalse($meta['used_fallback']);
        self::assertFalse($meta['used_stale_cache']);
    }

    public function testGenerateTopProjectsExtractsJsonWhenGeminiAddsTextAroundIt(): void
    {
        $service = $this->createServiceFromText(<<<'TEXT'
Voici le resultat demande.
{
  "projects": [
    {
      "name": "Tesla Energy",
      "country": "Unknownland",
      "annual_revenue": "12 milliards USD",
      "description": "Branche energie en forte croissance.",
      "founded": "2015",
      "why_top": "Capacite d innovation rapide."
    }
  ]
}
Merci.
TEXT);

        $projects = $service->generateTopProjects('Energie');

        self::assertCount(1, $projects);
        self::assertSame(1, $projects[0]['rank']);
        self::assertSame('🌍', $projects[0]['country_flag']);
        self::assertSame('Tesla Energy', $projects[0]['name']);
    }

    public function testGenerateTopProjectsTrimsToTenItemsAndUsesBlueDecorationAfterTopThree(): void
    {
        $projects = [];
        for ($index = 1; $index <= 12; ++$index) {
            $projects[] = [
                'rank' => $index,
                'name' => 'Project ' . $index,
                'country' => 'France',
                'annual_revenue' => $index . ' milliards USD',
                'description' => 'Description ' . $index,
                'founded' => '200' . ($index % 10),
                'why_top' => 'Reason ' . $index,
            ];
        }

        $service = $this->createServiceFromApiPayload([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode(['projects' => $projects], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $normalizedProjects = $service->generateTopProjects('Transport');

        self::assertCount(10, $normalizedProjects);
        self::assertSame('top10-rank-blue', $normalizedProjects[9]['border_class']);
        self::assertSame(10, $normalizedProjects[9]['rank']);
        self::assertLessThan(100, $normalizedProjects[9]['progress']);
    }

    public function testGenerateTopProjectsThrowsImmediateFrenchErrorForInvalidApiKey(): void
    {
        $attempts = 0;

        $service = new GeminiTopProjectsService(
            new MockHttpClient(function () use (&$attempts): ResponseInterface {
                ++$attempts;

                return new MockResponse(
                    json_encode(['error' => ['message' => 'API key not valid']], JSON_THROW_ON_ERROR),
                    ['http_code' => 403]
                );
            }),
            new ArrayAdapter(),
            new NullLogger(),
            'test-api-key',
            'gemini-2.5-flash',
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.5-flash-lite'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La cle API Gemini est invalide');

        try {
            $service->generateTopProjects('Finance');
        } finally {
            self::assertSame(1, $attempts);
        }
    }

    public function testGenerateTopProjectsThrowsImmediateFrenchErrorForQuotaExceeded(): void
    {
        $attempts = 0;

        $service = new GeminiTopProjectsService(
            new MockHttpClient(function () use (&$attempts): ResponseInterface {
                ++$attempts;

                return new MockResponse(
                    json_encode([
                        'error' => [
                            'message' => 'Quota exceeded for metric: generativelanguage.googleapis.com/generate_content_free_tier_requests, limit: 0, model: gemini-2.0-flash',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    ['http_code' => 429]
                );
            }),
            new ArrayAdapter(),
            new NullLogger(),
            'test-api-key',
            'gemini-2.5-flash',
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.5-flash-lite'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le quota Gemini est indisponible');

        try {
            $service->generateTopProjects('Finance');
        } finally {
            self::assertSame(1, $attempts);
        }
    }

    public function testGenerateTopProjectsFallsBackToSecondaryModelAndTracksMeta(): void
    {
        $urls = [];

        $service = new GeminiTopProjectsService(
            new MockHttpClient(function (string $method, string $url) use (&$urls): ResponseInterface {
                $urls[] = $url;

                if (count($urls) === 1) {
                    return new MockResponse(
                        json_encode([
                            'error' => [
                                'message' => 'This model is currently experiencing high demand. Please try again later.',
                            ],
                        ], JSON_THROW_ON_ERROR),
                        ['http_code' => 503]
                    );
                }

                return new MockResponse(json_encode([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => '{"projects":[{"name":"Nvidia","country":"United States","annual_revenue":"60 milliards USD","description":"Leader des puces IA.","founded":"1993","why_top":"Position cle sur l infrastructure IA."}]}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
            }),
            new ArrayAdapter(),
            new NullLogger(),
            'test-api-key',
            'gemini-2.5-flash',
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.5-flash-lite'
        );

        $projects = $service->generateTopProjects('IT / Technologie');
        $meta = $service->getLastGenerationMeta();

        self::assertCount(2, $urls);
        self::assertStringContainsString('models/gemini-2.5-flash:generateContent', $urls[0]);
        self::assertStringContainsString('models/gemini-2.5-flash-lite:generateContent', $urls[1]);
        self::assertCount(1, $projects);
        self::assertSame('Nvidia', $projects[0]['name']);
        self::assertSame('live', $meta['source']);
        self::assertSame('gemini-2.5-flash-lite', $meta['model']);
        self::assertTrue($meta['used_fallback']);
        self::assertSame('Classement genere via le modele de secours Gemini.', $meta['warning']);
    }

    public function testGenerateTopProjectsReturnsFreshCachedProjectsWithoutCallingApi(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('gemini_top_projects_' . sha1('finance'));
        $item->set([
            'generated_at' => time(),
            'model' => 'gemini-2.5-flash',
            'projects' => [
                [
                    'rank' => 1,
                    'name' => 'Cached Project',
                    'country' => 'France',
                    'country_flag' => 'FR',
                    'annual_revenue' => '10 milliards USD',
                    'description' => 'Depuis le cache.',
                    'founded' => '2010',
                    'why_top' => 'Cache warm.',
                    'medal' => 'A',
                    'border_class' => 'top10-rank-gold',
                    'progress' => 100,
                ],
            ],
        ]);
        $cache->save($item);

        $service = new GeminiTopProjectsService(
            new MockHttpClient(function (): ResponseInterface {
                self::fail('The API should not be called when a fresh cached result exists.');
            }),
            $cache,
            new NullLogger(),
            'test-api-key',
            'gemini-2.5-flash',
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.5-flash-lite'
        );

        $projects = $service->generateTopProjects('Finance');
        $meta = $service->getLastGenerationMeta();

        self::assertCount(1, $projects);
        self::assertSame('Cached Project', $projects[0]['name']);
        self::assertSame('fresh_cache', $meta['source']);
        self::assertSame('gemini-2.5-flash', $meta['model']);
        self::assertFalse($meta['used_stale_cache']);
    }

    public function testGenerateTopProjectsReturnsStaleCachedProjectsWhenAllModelsFailTransiently(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('gemini_top_projects_' . sha1('finance'));
        $item->set([
            'generated_at' => time() - 500000,
            'model' => 'gemini-2.5-flash',
            'projects' => [
                [
                    'rank' => 1,
                    'name' => 'Stale Project',
                    'country' => 'France',
                    'country_flag' => 'FR',
                    'annual_revenue' => '8 milliards USD',
                    'description' => 'Depuis un ancien cache.',
                    'founded' => '2008',
                    'why_top' => 'Toujours utile.',
                    'medal' => 'A',
                    'border_class' => 'top10-rank-gold',
                    'progress' => 100,
                ],
            ],
        ]);
        $cache->save($item);

        $service = new GeminiTopProjectsService(
            new MockHttpClient([
                new MockResponse(
                    json_encode([
                        'error' => [
                            'message' => 'This model is currently experiencing high demand. Please try again later.',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    ['http_code' => 503]
                ),
                new MockResponse(
                    json_encode([
                        'error' => [
                            'message' => 'This model is currently experiencing high demand. Please try again later.',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    ['http_code' => 503]
                ),
            ]),
            $cache,
            new NullLogger(),
            'test-api-key',
            'gemini-2.5-flash',
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.5-flash-lite'
        );

        $projects = $service->generateTopProjects('Finance');
        $meta = $service->getLastGenerationMeta();

        self::assertCount(1, $projects);
        self::assertSame('Stale Project', $projects[0]['name']);
        self::assertSame('stale_cache', $meta['source']);
        self::assertSame('gemini-2.5-flash', $meta['model']);
        self::assertTrue($meta['used_stale_cache']);
        self::assertSame('Affichage du dernier classement disponible en cache, car Gemini est temporairement indisponible.', $meta['warning']);
    }

    public function testGenerateTopProjectsThrowsCleanFrenchErrorWhenNoCacheAndAllModelsFail(): void
    {
        $service = new GeminiTopProjectsService(
            new MockHttpClient([
                new MockResponse(
                    json_encode([
                        'error' => [
                            'message' => 'This model is currently experiencing high demand. Please try again later.',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    ['http_code' => 503]
                ),
                new MockResponse(
                    json_encode([
                        'error' => [
                            'message' => 'This model is currently experiencing high demand. Please try again later.',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    ['http_code' => 503]
                ),
            ]),
            new ArrayAdapter(),
            new NullLogger(),
            'test-api-key',
            'gemini-2.5-flash',
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.5-flash-lite'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le service Gemini est temporairement indisponible pour ce classement.');

        $service->generateTopProjects('Finance');
    }

    public function testGenerateTopProjectsUsesConfiguredBaseUrlAndParsesFallbackList(): void
    {
        $urls = [];

        $service = new GeminiTopProjectsService(
            new MockHttpClient(function (string $method, string $url) use (&$urls): ResponseInterface {
                $urls[] = $url;

                if (count($urls) === 1) {
                    return new MockResponse(
                        json_encode([
                            'error' => [
                                'message' => 'Service unavailable',
                            ],
                        ], JSON_THROW_ON_ERROR),
                        ['http_code' => 503]
                    );
                }

                return new MockResponse(json_encode([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => '{"projects":[{"name":"Stripe","country":"United States","annual_revenue":"14 milliards USD","description":"Infrastructure de paiement.","founded":"2010","why_top":"Execution produit."}]}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
            }),
            new ArrayAdapter(),
            new NullLogger(),
            'test-api-key',
            'models/gemini-2.5-flash',
            'https://example.test/v1beta/',
            'models/gemini-2.5-flash-lite, gemini-2.0-flash'
        );

        $service->generateTopProjects('Finance');

        self::assertSame(
            'https://example.test/v1beta/models/gemini-2.5-flash:generateContent?key=test-api-key',
            $urls[0]
        );
        self::assertSame(
            'https://example.test/v1beta/models/gemini-2.5-flash-lite:generateContent?key=test-api-key',
            $urls[1]
        );
    }

    private function createServiceFromText(string $text): GeminiTopProjectsService
    {
        return $this->createServiceFromApiPayload([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => $text,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createServiceFromApiPayload(array $payload): GeminiTopProjectsService
    {
        return new GeminiTopProjectsService(
            new MockHttpClient([
                new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]),
            ]),
            new ArrayAdapter(),
            new NullLogger(),
            'test-api-key',
            'gemini-2.5-flash',
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.5-flash-lite'
        );
    }
}
