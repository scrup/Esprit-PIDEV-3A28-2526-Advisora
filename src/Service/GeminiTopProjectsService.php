<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiTopProjectsTransientException extends \RuntimeException
{
}

final class GeminiTopProjectsPermanentException extends \RuntimeException
{
}

final class GeminiTopProjectsService
{
    private const FRESH_CACHE_TTL = 43200;
    private const STALE_CACHE_TTL = 604800;
    private const MAX_OUTPUT_TOKENS = 1200;
    private const DEFAULT_FALLBACK_MODELS = 'gemini-2.5-flash-lite';

    /**
     * @var array{source: string|null, model: string|null, used_fallback: bool, used_stale_cache: bool, warning: string|null}
     */
    private array $lastGenerationMeta = [
        'source' => null,
        'model' => null,
        'used_fallback' => false,
        'used_stale_cache' => false,
        'warning' => null,
    ];

    /**
     * @var array<string, string>
     */
    private const COUNTRY_FLAGS = [
        'allemagne' => '🇩🇪',
        'argentine' => '🇦🇷',
        'argentina' => '🇦🇷',
        'australia' => '🇦🇺',
        'australie' => '🇦🇺',
        'belgique' => '🇧🇪',
        'brazil' => '🇧🇷',
        'bresil' => '🇧🇷',
        'canada' => '🇨🇦',
        'chine' => '🇨🇳',
        'china' => '🇨🇳',
        'coree du sud' => '🇰🇷',
        'denmark' => '🇩🇰',
        'danemark' => '🇩🇰',
        'egypte' => '🇪🇬',
        'egypt' => '🇪🇬',
        'emirats arabes unis' => '🇦🇪',
        'emirates arabes unis' => '🇦🇪',
        'espagne' => '🇪🇸',
        'france' => '🇫🇷',
        'germany' => '🇩🇪',
        'inde' => '🇮🇳',
        'india' => '🇮🇳',
        'indonesie' => '🇮🇩',
        'indonesia' => '🇮🇩',
        'irlande' => '🇮🇪',
        'ireland' => '🇮🇪',
        'italie' => '🇮🇹',
        'italy' => '🇮🇹',
        'japon' => '🇯🇵',
        'japan' => '🇯🇵',
        'luxembourg' => '🇱🇺',
        'maroc' => '🇲🇦',
        'morocco' => '🇲🇦',
        'mexique' => '🇲🇽',
        'mexico' => '🇲🇽',
        'netherlands' => '🇳🇱',
        'new zealand' => '🇳🇿',
        'nouvelle-zelande' => '🇳🇿',
        'nouvelle zelande' => '🇳🇿',
        'pays-bas' => '🇳🇱',
        'portugal' => '🇵🇹',
        'qatar' => '🇶🇦',
        'royaume-uni' => '🇬🇧',
        'royaume uni' => '🇬🇧',
        'singapour' => '🇸🇬',
        'singapore' => '🇸🇬',
        'south korea' => '🇰🇷',
        'suede' => '🇸🇪',
        'sweden' => '🇸🇪',
        'suisse' => '🇨🇭',
        'switzerland' => '🇨🇭',
        'tunisie' => '🇹🇳',
        'tunisia' => '🇹🇳',
        'turquie' => '🇹🇷',
        'turkey' => '🇹🇷',
        'uk' => '🇬🇧',
        'united arab emirates' => '🇦🇪',
        'united kingdom' => '🇬🇧',
        'united states' => '🇺🇸',
        'united states of america' => '🇺🇸',
        'usa' => '🇺🇸',
        'etats-unis' => '🇺🇸',
        'etats unis' => '🇺🇸',
        'etats-unis d amerique' => '🇺🇸',
        'etats unis d amerique' => '🇺🇸',
        'viet nam' => '🇻🇳',
        'vietnam' => '🇻🇳',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cachePool,
        private LoggerInterface $logger,
        private ?string $apiKey = null,
        private ?string $model = null,
        private ?string $baseUrl = null,
        private ?string $fallbackModels = null
    ) {
        $this->apiKey = $this->resolveFirstNonEmpty(
            $this->apiKey,
            $this->readEnv('GEMINI_API_KEY'),
            $this->readEnv('GOOGLE_API_KEY')
        );

        $this->model = $this->normalizeModelName(
            $this->resolveFirstNonEmpty($this->model, $this->readEnv('GEMINI_MODEL')) ?? 'gemini-2.5-flash'
        );

        $this->baseUrl = rtrim(
            $this->resolveFirstNonEmpty($this->baseUrl, $this->readEnv('GEMINI_API_BASE_URL'))
                ?? 'https://generativelanguage.googleapis.com/v1beta',
            '/'
        );

        $this->fallbackModels = $this->resolveFirstNonEmpty(
            $this->fallbackModels,
            $this->readEnv('GEMINI_FALLBACK_MODELS')
        ) ?? self::DEFAULT_FALLBACK_MODELS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generateTopProjects(string $projectType): array
    {
        $this->resetLastGenerationMeta();

        $projectType = trim($projectType);
        if ($projectType === '') {
            throw new \RuntimeException('Merci de selectionner un type de projet avant de lancer la generation.');
        }

        $cachedEntry = $this->readCacheEntry($projectType);
        $cachedProjects = $this->extractCachedProjects($cachedEntry);
        if ($cachedProjects !== null && $this->isFreshCacheEntry($cachedEntry)) {
            $this->recordGenerationMeta('fresh_cache', $this->extractCachedModel($cachedEntry), null);

            return $cachedProjects;
        }

        if ($this->apiKey === null || trim($this->apiKey) === '') {
            $this->logger->error('Gemini Top 10 request aborted because GEMINI_API_KEY is missing.');

            throw new \RuntimeException('La cle API Gemini est absente. Configurez GEMINI_API_KEY ou GOOGLE_API_KEY avant d utiliser cette page.');
        }

        $prompt = $this->buildPrompt($projectType);
        $models = $this->buildModelCandidates();
        $lastTransientException = null;

        foreach ($models as $index => $model) {
            try {
                $projects = $this->requestProjectsFromModel($projectType, $prompt, $model);
                $this->writeCacheEntry($projectType, $projects, $model);
                $warning = $index > 0 ? 'Classement genere via le modele de secours Gemini.' : null;
                $this->recordGenerationMeta('live', $model, $warning);

                return $projects;
            } catch (GeminiTopProjectsTransientException $exception) {
                $lastTransientException = $exception;

                continue;
            } catch (GeminiTopProjectsPermanentException $exception) {
                throw new \RuntimeException($exception->getMessage(), 0, $exception);
            }
        }

        if ($cachedProjects !== null) {
            $warning = 'Affichage du dernier classement disponible en cache, car Gemini est temporairement indisponible.';
            $this->recordGenerationMeta('stale_cache', $this->extractCachedModel($cachedEntry), $warning);

            return $cachedProjects;
        }

        throw new \RuntimeException(
            $lastTransientException?->getMessage()
                ?: 'Le service Gemini est temporairement indisponible pour ce classement. Merci de reessayer dans quelques instants.'
        );
    }

    /**
     * @return array{source: string|null, model: string|null, used_fallback: bool, used_stale_cache: bool, warning: string|null}
     */
    public function getLastGenerationMeta(): array
    {
        return $this->lastGenerationMeta;
    }

    private function buildEndpoint(string $model): string
    {
        return sprintf(
            '%s/models/%s:generateContent?key=%s',
            $this->baseUrl,
            rawurlencode($model),
            rawurlencode($this->apiKey ?? '')
        );
    }

    private function buildPrompt(string $projectType): string
    {
        return sprintf(
            'Secteur: %s. Retourne uniquement du JSON compact avec la cle "projects". Donne 10 projets ou entreprises reels, tries par chiffre d affaires annuel decroissant. Champs requis pour chaque element: rank, name, country, annual_revenue, description, founded, why_top. Description et why_top: 1 phrase courte en francais. Pas de markdown, pas de texte autour.',
            $projectType
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestPayload(string $prompt): array
    {
        return [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $this->getResponseSchema(),
                'temperature' => 0.2,
                'maxOutputTokens' => self::MAX_OUTPUT_TOKENS,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requestProjectsFromModel(string $projectType, string $prompt, string $model): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->buildEndpoint($model), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $this->buildRequestPayload($prompt),
                'timeout' => 45,
            ]);

            $statusCode = $response->getStatusCode();
            $rawPayload = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Gemini Top 10 transport failure.', [
                'project_type' => $projectType,
                'model' => $model,
                'error' => $exception->getMessage(),
            ]);

            throw new GeminiTopProjectsTransientException(
                'Le service Gemini est temporairement indisponible pour ce classement. Merci de reessayer dans quelques instants.',
                0,
                $exception
            );
        }

        try {
            $payload = $this->decodeApiPayload($rawPayload, $projectType);
        } catch (\RuntimeException $exception) {
            $this->logger->warning('Gemini Top 10 returned an unusable API payload.', [
                'project_type' => $projectType,
                'model' => $model,
            ]);

            throw new GeminiTopProjectsTransientException(
                'Le service Gemini est temporairement indisponible pour ce classement. Merci de reessayer dans quelques instants.',
                0,
                $exception
            );
        }

        if ($statusCode >= 400) {
            $errorMessage = trim((string) ($payload['error']['message'] ?? ''));

            if ($this->shouldTryNextModelAfterApiError($statusCode, $errorMessage)) {
                $this->logger->warning('Gemini Top 10 model failed with a transient API error.', [
                    'project_type' => $projectType,
                    'model' => $model,
                    'status_code' => $statusCode,
                    'error_message' => $errorMessage,
                ]);

                throw new GeminiTopProjectsTransientException(
                    'Le service Gemini est temporairement indisponible pour ce classement. Merci de reessayer dans quelques instants.'
                );
            }

            $this->logger->error('Gemini Top 10 model failed with a permanent API error.', [
                'project_type' => $projectType,
                'model' => $model,
                'status_code' => $statusCode,
                'error_message' => $errorMessage,
            ]);

            throw new GeminiTopProjectsPermanentException($this->buildUserFacingApiError($statusCode, $errorMessage, $model));
        }

        $candidateText = $this->extractCandidateText($payload);
        if ($candidateText === null) {
            $this->logger->warning('Gemini Top 10 response did not contain candidate text.', [
                'project_type' => $projectType,
                'model' => $model,
            ]);

            throw new GeminiTopProjectsTransientException(
                'Le service Gemini est temporairement indisponible pour ce classement. Merci de reessayer dans quelques instants.'
            );
        }

        try {
            $projects = $this->decodeProjectsPayload($candidateText, $projectType);
        } catch (\RuntimeException $exception) {
            $this->logger->warning('Gemini Top 10 returned an unusable projects payload.', [
                'project_type' => $projectType,
                'model' => $model,
            ]);

            throw new GeminiTopProjectsTransientException(
                'Le service Gemini est temporairement indisponible pour ce classement. Merci de reessayer dans quelques instants.',
                0,
                $exception
            );
        }

        if ($projects === []) {
            $this->logger->warning('Gemini Top 10 returned an empty project list.', [
                'project_type' => $projectType,
                'model' => $model,
            ]);

            throw new GeminiTopProjectsTransientException(
                'Le service Gemini est temporairement indisponible pour ce classement. Merci de reessayer dans quelques instants.'
            );
        }

        return $projects;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeApiPayload(string $rawPayload, string $projectType): array
    {
        try {
            $decoded = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('Gemini Top 10 returned invalid API JSON.', [
                'project_type' => $projectType,
                'payload_excerpt' => $this->truncate($rawPayload),
            ]);

            throw new \RuntimeException('La reponse brute de l API Gemini n est pas un JSON valide.', 0, $exception);
        }

        if (!is_array($decoded)) {
            $this->logger->error('Gemini Top 10 API payload format was unexpected.', [
                'project_type' => $projectType,
            ]);

            throw new \RuntimeException('Le format de reponse de Gemini est inattendu.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function getResponseSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['projects'],
            'properties' => [
                'projects' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'required' => ['rank', 'name', 'country', 'annual_revenue', 'description', 'founded', 'why_top'],
                        'properties' => [
                            'rank' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'country' => ['type' => 'string'],
                            'annual_revenue' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'founded' => ['type' => 'string'],
                            'why_top' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractCandidateText(array $payload): ?string
    {
        $texts = [];

        foreach (($payload['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (!is_array($part)) {
                continue;
            }

            $text = trim((string) ($part['text'] ?? ''));

            if ($text !== '') {
                $texts[] = $text;
            }
        }

        if ($texts === []) {
            return null;
        }

        return implode("\n", $texts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeProjectsPayload(string $candidateText, string $projectType): array
    {
        $jsonDocument = $this->extractJsonDocument($candidateText);

        try {
            $decoded = json_decode($jsonDocument, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('Gemini Top 10 candidate text was not valid JSON.', [
                'project_type' => $projectType,
                'payload_excerpt' => $this->truncate($candidateText),
            ]);

            throw new \RuntimeException('Gemini a retourne un texte qui ne peut pas etre converti en JSON valide.', 0, $exception);
        }

        if (!is_array($decoded) || !isset($decoded['projects']) || !is_array($decoded['projects'])) {
            $this->logger->error('Gemini Top 10 JSON did not contain the expected projects key.', [
                'project_type' => $projectType,
                'decoded_keys' => is_array($decoded) ? array_keys($decoded) : [],
            ]);

            throw new \RuntimeException('Le JSON retourne par Gemini ne contient pas la cle "projects" attendue.');
        }

        return $this->normalizeProjects($decoded['projects']);
    }

    private function extractJsonDocument(string $responseText): string
    {
        $responseText = trim($responseText);

        if (preg_match('/```(?:json)?\s*(.+?)\s*```/is', $responseText, $matches) === 1) {
            $responseText = trim($matches[1]);
        }

        if (str_starts_with($responseText, '{') && str_ends_with($responseText, '}')) {
            return $responseText;
        }

        $start = strpos($responseText, '{');
        $end = strrpos($responseText, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($responseText, $start, $end - $start + 1);
        }

        return $responseText;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProjects(mixed $projects): array
    {
        if (!is_array($projects)) {
            return [];
        }

        $normalized = [];

        foreach ($projects as $index => $project) {
            if (!is_array($project)) {
                continue;
            }

            $normalized[] = $this->normalizeProject($project, count($normalized) + 1, $index + 1);
        }

        usort($normalized, static fn (array $left, array $right): int => $left['sort_rank'] <=> $right['sort_rank']);
        $normalized = array_slice($normalized, 0, 10);

        foreach ($normalized as $index => &$project) {
            $displayRank = $index + 1;
            $decoration = $this->buildRankDecoration($displayRank);

            $project['rank'] = $displayRank;
            $project['medal'] = $decoration['medal'];
            $project['border_class'] = $decoration['border_class'];
            $project['progress'] = $decoration['progress'];
            unset($project['sort_rank']);
        }
        unset($project);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $project
     *
     * @return array<string, mixed>
     */
    private function normalizeProject(array $project, int $fallbackRank, int $sortRank): array
    {
        $country = $this->cleanText($project['country'] ?? null, 'Pays non renseigne');

        return [
            'sort_rank' => $this->normalizeRank($project['rank'] ?? null, $fallbackRank, $sortRank),
            'name' => $this->cleanText($project['name'] ?? null, 'Projet non renseigne'),
            'country' => $country,
            'country_flag' => $this->resolveCountryFlag($country),
            'annual_revenue' => $this->cleanText($project['annual_revenue'] ?? null, 'Non communique'),
            'description' => $this->cleanText($project['description'] ?? null, 'Description non disponible.'),
            'founded' => $this->cleanText($project['founded'] ?? null, 'Non communiquee'),
            'why_top' => $this->cleanText($project['why_top'] ?? null, 'Raison non communiquee.'),
        ];
    }

    private function normalizeRank(mixed $rank, int $fallbackRank, int $sortRank): int
    {
        if (is_numeric($rank)) {
            $normalizedRank = (int) $rank;

            if ($normalizedRank > 0) {
                return $normalizedRank;
            }
        }

        if ($fallbackRank > 0) {
            return $fallbackRank;
        }

        return $sortRank;
    }

    /**
     * @return array{medal: string, border_class: string, progress: int}
     */
    private function buildRankDecoration(int $rank): array
    {
        return match ($rank) {
            1 => ['medal' => '🥇', 'border_class' => 'top10-rank-gold', 'progress' => 100],
            2 => ['medal' => '🥈', 'border_class' => 'top10-rank-silver', 'progress' => 92],
            3 => ['medal' => '🥉', 'border_class' => 'top10-rank-bronze', 'progress' => 84],
            default => [
                'medal' => '',
                'border_class' => 'top10-rank-blue',
                'progress' => max(30, 100 - (($rank - 1) * 8)),
            ],
        };
    }

    private function resolveCountryFlag(string $country): string
    {
        $normalizedCountry = $this->normalizeLookupKey($country);

        return match ($normalizedCountry) {
            'usa',
            'united states',
            'united states of america',
            'etats-unis',
            'etats unis',
            'etats-unis d amerique',
            'etats unis d amerique' => "\u{1F1FA}\u{1F1F8}",
            default => self::COUNTRY_FLAGS[$normalizedCountry] ?? "\u{1F30D}",
        };
    }

    private function normalizeLookupKey(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = str_replace(
            ['à', 'á', 'â', 'ä', 'ã', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'ö', 'õ', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', "'", '’'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', ' ', ' '],
            $value
        );

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function cleanText(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : $fallback;
    }

    private function truncate(string $value, int $length = 500): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length - 3) . '...';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCacheEntry(string $projectType): ?array
    {
        $item = $this->cachePool->getItem($this->buildCacheKey($projectType));

        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     */
    private function writeCacheEntry(string $projectType, array $projects, string $model): void
    {
        $item = $this->cachePool->getItem($this->buildCacheKey($projectType));
        $item->set([
            'generated_at' => time(),
            'model' => $model,
            'projects' => $projects,
        ]);
        $item->expiresAfter(self::STALE_CACHE_TTL);
        $this->cachePool->save($item);
    }

    /**
     * @param array<string, mixed>|null $entry
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function extractCachedProjects(?array $entry): ?array
    {
        if ($entry === null) {
            return null;
        }

        $projects = $entry['projects'] ?? null;

        return is_array($projects) && $projects !== [] ? $projects : null;
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function extractCachedModel(?array $entry): ?string
    {
        $model = $entry['model'] ?? null;

        return is_string($model) && trim($model) !== '' ? trim($model) : null;
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function isFreshCacheEntry(?array $entry): bool
    {
        if ($entry === null) {
            return false;
        }

        $generatedAt = $entry['generated_at'] ?? null;

        if (!is_int($generatedAt) || $generatedAt <= 0) {
            return false;
        }

        return (time() - $generatedAt) <= self::FRESH_CACHE_TTL;
    }

    private function buildCacheKey(string $projectType): string
    {
        return 'gemini_top_projects_' . sha1($this->normalizeLookupKey($projectType));
    }

    private function buildUserFacingApiError(int $statusCode, string $errorMessage, string $model): string
    {
        $normalizedMessage = mb_strtolower(trim($errorMessage));

        if (
            $statusCode === 429
            || str_contains($normalizedMessage, 'quota exceeded')
            || str_contains($normalizedMessage, 'rate limit')
            || str_contains($normalizedMessage, 'billing')
            || str_contains($normalizedMessage, 'limit: 0')
        ) {
            return sprintf(
                'Le quota Gemini est indisponible pour le modele %s sur cette cle API. Verifiez la facturation et les limites du projet Google, ou utilisez une autre cle, puis reessayez.',
                $model
            );
        }

        if (
            $statusCode === 401
            || $statusCode === 403
            || str_contains($normalizedMessage, 'api key not valid')
            || str_contains($normalizedMessage, 'permission denied')
        ) {
            return 'La cle API Gemini est invalide ou n a pas acces a ce modele. Verifiez GEMINI_API_KEY, GOOGLE_API_KEY et le projet Google associe.';
        }

        return 'Le service Gemini est temporairement indisponible pour ce classement. Merci de reessayer dans quelques instants.';
    }

    private function shouldTryNextModelAfterApiError(int $statusCode, string $errorMessage): bool
    {
        return $this->isTransientHighDemandError($statusCode, $errorMessage);
    }

    private function isTransientHighDemandError(int $statusCode, string $errorMessage): bool
    {
        $normalizedMessage = mb_strtolower(trim($errorMessage));

        if (
            str_contains($normalizedMessage, 'quota exceeded')
            || str_contains($normalizedMessage, 'limit: 0')
            || str_contains($normalizedMessage, 'billing')
        ) {
            return false;
        }

        return $statusCode === 503
            || ($statusCode === 429 && (
                str_contains($normalizedMessage, 'too many requests')
                || str_contains($normalizedMessage, 'resource has been exhausted')
                || str_contains($normalizedMessage, 'try again later')
            ))
            || str_contains($normalizedMessage, 'currently experiencing high demand')
            || str_contains($normalizedMessage, 'spikes in demand are usually temporary')
            || str_contains($normalizedMessage, 'temporarily unavailable')
            || str_contains($normalizedMessage, 'service unavailable')
            || str_contains($normalizedMessage, 'please try again later');
    }

    private function resetLastGenerationMeta(): void
    {
        $this->lastGenerationMeta = [
            'source' => null,
            'model' => null,
            'used_fallback' => false,
            'used_stale_cache' => false,
            'warning' => null,
        ];
    }

    private function recordGenerationMeta(string $source, ?string $model, ?string $warning): void
    {
        $this->lastGenerationMeta = [
            'source' => $source,
            'model' => $model,
            'used_fallback' => $model !== null
                && $model !== ($this->model ?? 'gemini-2.5-flash')
                && $this->isFallbackModel($model),
            'used_stale_cache' => $source === 'stale_cache',
            'warning' => $warning,
        ];
    }

    /**
     * @return string[]
     */
    private function buildModelCandidates(): array
    {
        $models = [$this->model ?? 'gemini-2.5-flash'];

        foreach ($this->parseFallbackModels($this->fallbackModels ?? self::DEFAULT_FALLBACK_MODELS) as $fallbackModel) {
            if (!in_array($fallbackModel, $models, true)) {
                $models[] = $fallbackModel;
            }
        }

        return $models;
    }

    /**
     * @return string[]
     */
    private function parseFallbackModels(string $fallbackModels): array
    {
        $models = [];

        foreach (explode(',', $fallbackModels) as $model) {
            $normalizedModel = $this->normalizeModelName($model);

            if ($normalizedModel !== '' && !in_array($normalizedModel, $models, true)) {
                $models[] = $normalizedModel;
            }
        }

        return $models !== [] ? $models : [self::DEFAULT_FALLBACK_MODELS];
    }

    private function isFallbackModel(string $model): bool
    {
        return in_array($model, $this->parseFallbackModels($this->fallbackModels ?? self::DEFAULT_FALLBACK_MODELS), true);
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

    private function normalizeModelName(string $model): string
    {
        $model = trim($model);

        if (str_starts_with($model, 'models/')) {
            $model = substr($model, 7);
        }

        return $model !== '' ? $model : 'gemini-2.5-flash';
    }

    private function readEnv(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? $value : null;
    }
}