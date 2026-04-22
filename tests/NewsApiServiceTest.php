<?php

namespace App\Tests;

use App\Service\NewsApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

class NewsApiServiceTest extends TestCase
{
    public function testSearchProjectTypeNewsReturnsNormalizedLiveArticles(): void
    {
        $service = new NewsApiService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'status' => 'ok',
                    'articles' => [
                        [
                            'source' => ['name' => 'TechCrunch'],
                            'title' => 'AI logistics startup expands',
                            'description' => 'A new startup is scaling globally.',
                            'url' => 'https://example.test/article-1',
                            'urlToImage' => 'https://example.test/image-1.jpg',
                            'publishedAt' => '2026-04-22T10:00:00Z',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
            ]),
            new NullLogger(),
            'test-news-api-key',
            'https://newsapi.org/v2'
        );

        $articles = $service->searchProjectTypeNews('AI logistics');
        $meta = $service->getLastFetchMeta();

        self::assertCount(1, $articles);
        self::assertSame('AI logistics startup expands', $articles[0]['title']);
        self::assertSame('TechCrunch', $articles[0]['source']);
        self::assertSame('live', $meta['source']);
        self::assertSame('AI logistics', $meta['query']);
        self::assertNull($meta['warning']);
    }

    public function testSearchProjectTypeNewsReturnsEmptyListWhenApiFails(): void
    {
        $service = new NewsApiService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'status' => 'error',
                    'message' => 'backend unavailable',
                ], JSON_THROW_ON_ERROR), ['http_code' => 503]),
            ]),
            new NullLogger(),
            'test-news-api-key',
            'https://newsapi.org/v2'
        );

        $articles = $service->searchProjectTypeNews('Green hydrogen');
        $meta = $service->getLastFetchMeta();

        self::assertSame([], $articles);
        self::assertSame('none', $meta['source']);
        self::assertSame('Green hydrogen', $meta['query']);
        self::assertSame('Les actualites liees a ce secteur sont temporairement indisponibles.', $meta['warning']);
    }

    public function testSearchProjectTypeNewsNormalizesPartialArticlesSafely(): void
    {
        $service = new NewsApiService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'status' => 'ok',
                    'articles' => [
                        [
                            'source' => ['name' => 'Incomplete Source'],
                            'title' => '',
                            'url' => 'https://example.test/missing-title',
                        ],
                        [
                            'source' => ['name' => 'The Verge'],
                            'title' => 'Battery innovation advances',
                            'description' => '',
                            'url' => 'https://example.test/article-2',
                            'publishedAt' => '',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
            ]),
            new NullLogger(),
            'test-news-api-key',
            'https://newsapi.org/v2'
        );

        $articles = $service->searchProjectTypeNews('Battery innovation');

        self::assertCount(1, $articles);
        self::assertSame('Battery innovation advances', $articles[0]['title']);
        self::assertNull($articles[0]['description']);
        self::assertNull($articles[0]['published_at']);
    }

    public function testSearchProjectTypeNewsReturnsEmptyListWhenQueryIsEmpty(): void
    {
        $service = new NewsApiService(
            new MockHttpClient(function (): ResponseInterface {
                self::fail('The NewsAPI should not be called when the query is empty.');
            }),
            new NullLogger(),
            'test-news-api-key',
            'https://newsapi.org/v2'
        );

        $articles = $service->searchProjectTypeNews('   ');

        self::assertSame([], $articles);
    }

    public function testSearchProjectTypeNewsReturnsEmptyListWhenApiKeyIsMissing(): void
    {
        $previousEnv = $_ENV['NEWS_API_KEY'] ?? null;
        $previousServer = $_SERVER['NEWS_API_KEY'] ?? null;
        putenv('NEWS_API_KEY');
        unset($_ENV['NEWS_API_KEY'], $_SERVER['NEWS_API_KEY']);

        try {
            $service = new NewsApiService(
                new MockHttpClient(function (): ResponseInterface {
                    self::fail('The NewsAPI should not be called when the API key is missing.');
                }),
                new NullLogger(),
                null,
                'https://newsapi.org/v2'
            );

            $articles = $service->searchProjectTypeNews('Climate tech');
            $meta = $service->getLastFetchMeta();

            self::assertSame([], $articles);
            self::assertSame('none', $meta['source']);
            self::assertSame('Climate tech', $meta['query']);
            self::assertSame('Les actualites ne sont pas disponibles pour le moment.', $meta['warning']);
        } finally {
            if ($previousEnv !== null) {
                $_ENV['NEWS_API_KEY'] = $previousEnv;
            }

            if ($previousServer !== null) {
                $_SERVER['NEWS_API_KEY'] = $previousServer;
            }

            if ($previousEnv !== null) {
                putenv('NEWS_API_KEY=' . $previousEnv);
            } elseif ($previousServer !== null) {
                putenv('NEWS_API_KEY=' . $previousServer);
            } else {
                putenv('NEWS_API_KEY');
            }
        }
    }
}
