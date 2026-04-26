<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NewsApiService
{
    private const DEFAULT_BASE_URL = 'https://newsapi.org/v2';
    private const DEFAULT_LANGUAGE = 'en';
    private const DEFAULT_SORT_BY = 'publishedAt';
    private const DEFAULT_PAGE_SIZE = 10;
    private const DEFAULT_SEARCH_IN = 'title,description';

    /**
     * @var array{source: string|null, query: string|null, warning: string|null, used_stale_cache: bool}
     */
    private array $lastFetchMeta = [
        'source' => null,
        'query' => null,
        'warning' => null,
        'used_stale_cache' => false,
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private ?string $apiKey = null,
        private ?string $baseUrl = null
    ) {
        $this->apiKey = $this->resolveFirstNonEmpty($this->apiKey, $this->readEnv('NEWS_API_KEY'));
        $this->baseUrl = rtrim(
            $this->resolveFirstNonEmpty($this->baseUrl, $this->readEnv('NEWS_API_BASE_URL')) ?? self::DEFAULT_BASE_URL,
            '/'
        );
    }

    /**
     * @return array<int, array{title: string, description: ?string, url: string, source: string, published_at: ?string, image_url: ?string}>
     */
    public function searchProjectTypeNews(string $projectType): array
    {
        $this->resetLastFetchMeta();

        $query = trim($projectType);
        $this->lastFetchMeta['query'] = $query;

        if ($query === '') {
            return [];
        }

        if ($this->apiKey === null || trim($this->apiKey) === '') {
            $this->logger->warning('NewsAPI request skipped because NEWS_API_KEY is missing.', [
                'query' => $query,
            ]);

            $this->recordFetchMeta('none', $query, 'Les actualites ne sont pas disponibles pour le moment.', false);

            return [];
        }

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/everything', [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                ],
                'query' => [
                    'q' => $query,
                    'language' => self::DEFAULT_LANGUAGE,
                    'sortBy' => self::DEFAULT_SORT_BY,
                    'pageSize' => self::DEFAULT_PAGE_SIZE,
                    'searchIn' => self::DEFAULT_SEARCH_IN,
                ],
                'timeout' => 20,
            ]);

            $statusCode = $response->getStatusCode();
            $rawPayload = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('NewsAPI transport failure.', [
                'query' => $query,
                'error' => $exception->getMessage(),
            ]);

            $this->recordFetchMeta('none', $query, 'Les actualites liees a ce secteur sont temporairement indisponibles.', false);

            return [];
        }

        $payload = $this->decodeApiPayload($rawPayload, $query);
        if ($statusCode >= 400 || ($payload['status'] ?? 'ok') !== 'ok') {
            $this->logger->warning('NewsAPI returned an error response.', [
                'query' => $query,
                'status_code' => $statusCode,
                'message' => $payload['message'] ?? null,
                'code' => $payload['code'] ?? null,
            ]);

            $this->recordFetchMeta('none', $query, 'Les actualites liees a ce secteur sont temporairement indisponibles.', false);

            return [];
        }

        $articles = $this->normalizeArticles($payload['articles'] ?? []);
        if ($articles === []) {
            $this->recordFetchMeta('none', $query, 'Aucune actualite recente trouvee pour ce secteur.', false);

            return [];
        }

        $this->recordFetchMeta('live', $query, null, false);

        return $articles;
    }

    /**
     * @return array{source: string|null, query: string|null, warning: string|null, used_stale_cache: bool}
     */
    public function getLastFetchMeta(): array
    {
        return $this->lastFetchMeta;
    }

    private function resetLastFetchMeta(): void
    {
        $this->lastFetchMeta = [
            'source' => null,
            'query' => null,
            'warning' => null,
            'used_stale_cache' => false,
        ];
    }

    private function recordFetchMeta(string $source, string $query, ?string $warning, bool $usedStaleCache): void
    {
        $this->lastFetchMeta = [
            'source' => $source,
            'query' => $query,
            'warning' => $warning,
            'used_stale_cache' => $usedStaleCache,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeApiPayload(string $rawPayload, string $query): array
    {
        try {
            $decoded = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->warning('NewsAPI returned invalid JSON.', [
                'query' => $query,
            ]);

            return ['status' => 'error', 'message' => 'invalid_json'];
        }

        return is_array($decoded) ? $decoded : ['status' => 'error', 'message' => 'invalid_payload'];
    }

    /**
     * @param mixed $articles
     *
     * @return array<int, array{title: string, description: ?string, url: string, source: string, published_at: ?string, image_url: ?string}>
     */
    private function normalizeArticles(mixed $articles): array
    {
        if (!is_array($articles)) {
            return [];
        }

        $normalized = [];

        foreach ($articles as $article) {
            if (!is_array($article)) {
                continue;
            }

            $title = trim((string) ($article['title'] ?? ''));
            $url = trim((string) ($article['url'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }

            $sourceName = trim((string) ($article['source']['name'] ?? 'Source inconnue'));
            $description = trim((string) ($article['description'] ?? ''));
            $publishedAt = trim((string) ($article['publishedAt'] ?? ''));
            $imageUrl = trim((string) ($article['urlToImage'] ?? ''));

            $normalized[] = [
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'url' => $url,
                'source' => $sourceName !== '' ? $sourceName : 'Source inconnue',
                'published_at' => $publishedAt !== '' ? $publishedAt : null,
                'image_url' => $imageUrl !== '' ? $imageUrl : null,
            ];

            if (count($normalized) >= self::DEFAULT_PAGE_SIZE) {
                break;
            }
        }

        return $normalized;
    }

    private function resolveFirstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function readEnv(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? $value : null;
    }
}
