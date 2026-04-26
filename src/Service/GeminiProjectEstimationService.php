<?php

namespace App\Service;

use App\Dto\ProjectEstimationRequest;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiProjectEstimationService extends AbstractProjectEstimationAnalyzer
{
    private const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    private const DEFAULT_MODEL = 'gemini-2.5-flash';
    private const MAX_OUTPUT_TOKENS = 1200;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private ?string $apiKey = null,
        private ?string $baseUrl = null,
        private ?string $model = null
    ) {
        $this->apiKey = $this->resolveFirstNonEmpty($this->apiKey);
        $this->baseUrl = rtrim($this->resolveFirstNonEmpty($this->baseUrl) ?? self::DEFAULT_BASE_URL, '/');
        $this->model = $this->normalizeModelName($this->resolveFirstNonEmpty($this->model) ?? self::DEFAULT_MODEL);
    }

    public function estimate(ProjectEstimationRequest $request): array
    {
        $this->resetLastEstimationMeta();

        if ($this->apiKey === null || trim($this->apiKey) === '') {
            $this->logger->error('Gemini project estimation request aborted because GEMINI_API_KEY is missing.');

            throw new ProjectEstimationProviderException(
                'gemini',
                'La configuration Gemini est absente. Ajoutez GEMINI_API_KEY avant de lancer une estimation.',
                false
            );
        }

        try {
            $response = $this->httpClient->request('POST', $this->buildEndpoint(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $this->buildPayload($request),
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $rawPayload = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Gemini project estimation transport failure.', [
                'error' => $exception->getMessage(),
                'model' => $this->model,
            ]);

            throw new ProjectEstimationProviderException(
                'gemini',
                'Le service Gemini est temporairement indisponible pour cette estimation.',
                true,
                null,
                $exception
            );
        }

        $payload = $this->decodeApiPayload($rawPayload);
        if ($statusCode >= 400 || isset($payload['error'])) {
            $message = $this->extractApiErrorMessage($payload);
            $retryable = $this->isTransientApiError($statusCode, $message);

            $this->logger->warning('Gemini project estimation returned an error response.', [
                'status_code' => $statusCode,
                'message' => $message,
                'retryable' => $retryable,
                'model' => $this->model,
            ]);

            throw new ProjectEstimationProviderException(
                'gemini',
                $this->buildUserFacingApiError($statusCode, $message),
                $retryable,
                $statusCode
            );
        }

        $responseText = $this->extractCandidateText($payload);
        if ($responseText === '') {
            $this->logger->warning('Gemini project estimation response did not contain usable text.', [
                'model' => $this->model,
            ]);

            throw new ProjectEstimationProviderException(
                'gemini',
                'Le service Gemini a renvoye une reponse incomplete pour cette estimation.',
                true
            );
        }

        try {
            $result = $this->normalizeEstimation($this->decodeStructuredResponse($responseText));
        } catch (\RuntimeException $exception) {
            $this->logger->warning('Gemini project estimation response could not be normalized.', [
                'error' => $exception->getMessage(),
                'model' => $this->model,
            ]);

            throw new ProjectEstimationProviderException(
                'gemini',
                'Le service Gemini a renvoye une reponse inexploitable pour cette estimation.',
                true,
                null,
                $exception
            );
        }

        $this->recordLastEstimationMeta('gemini', $this->model);

        return $result;
    }

    private function buildEndpoint(): string
    {
        return sprintf(
            '%s/models/%s:generateContent?key=%s',
            $this->baseUrl,
            rawurlencode($this->model ?? self::DEFAULT_MODEL),
            rawurlencode($this->apiKey ?? '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(ProjectEstimationRequest $request): array
    {
        return [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $this->buildAnalysisInstructions() . "\n\n" . $this->buildInputText($request),
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
     * @return array<string, mixed>
     */
    private function decodeApiPayload(string $rawPayload): array
    {
        try {
            $decoded = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('Gemini project estimation returned invalid API JSON.', [
                'payload_excerpt' => $this->truncate($rawPayload),
            ]);

            throw new ProjectEstimationProviderException(
                'gemini',
                'La reponse brute de Gemini n est pas interpretable.',
                true,
                null,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new ProjectEstimationProviderException(
                'gemini',
                'Le format de reponse Gemini est inattendu.',
                true
            );
        }

        return $decoded;
    }

    private function extractApiErrorMessage(array $payload): string
    {
        $message = $payload['error']['message'] ?? $payload['message'] ?? 'Gemini request failed.';

        return is_string($message) ? trim($message) : 'Gemini request failed.';
    }

    private function extractCandidateText(array $payload): string
    {
        $texts = [];

        foreach (($payload['candidates'][0]['content']['parts'] ?? []) as $part) {
            $text = trim((string) ($part['text'] ?? ''));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return implode("\n", $texts);
    }

    private function buildUserFacingApiError(int $statusCode, string $message): string
    {
        $normalizedMessage = mb_strtolower($message);

        if (
            $statusCode === 401
            || $statusCode === 403
            || str_contains($normalizedMessage, 'api key not valid')
            || str_contains($normalizedMessage, 'permission denied')
        ) {
            return 'La cle Gemini configuree est invalide ou n a pas acces a cette fonctionnalite.';
        }

        if (
            $statusCode === 429
            || str_contains($normalizedMessage, 'quota exceeded')
            || str_contains($normalizedMessage, 'rate limit')
            || str_contains($normalizedMessage, 'billing')
            || str_contains($normalizedMessage, 'limit: 0')
        ) {
            return 'Le quota Gemini ne permet pas de generer cette estimation pour le moment.';
        }

        if ($statusCode >= 500) {
            return 'Le service Gemini est temporairement indisponible pour cette estimation.';
        }

        return 'Impossible de generer l estimation via Gemini pour le moment.';
    }

    private function isTransientApiError(int $statusCode, string $message): bool
    {
        $normalizedMessage = mb_strtolower(trim($message));

        if (
            str_contains($normalizedMessage, 'quota exceeded')
            || str_contains($normalizedMessage, 'limit: 0')
            || str_contains($normalizedMessage, 'billing')
        ) {
            return true;
        }

        if (
            $statusCode === 401
            || $statusCode === 403
            || str_contains($normalizedMessage, 'api key not valid')
            || str_contains($normalizedMessage, 'permission denied')
        ) {
            return false;
        }

        return $statusCode === 503
            || $statusCode >= 500
            || ($statusCode === 429 && (
                str_contains($normalizedMessage, 'rate limit')
                || str_contains($normalizedMessage, 'too many requests')
                || str_contains($normalizedMessage, 'resource has been exhausted')
                || str_contains($normalizedMessage, 'try again later')
            ))
            || str_contains($normalizedMessage, 'currently experiencing high demand')
            || str_contains($normalizedMessage, 'temporarily unavailable')
            || str_contains($normalizedMessage, 'service unavailable')
            || str_contains($normalizedMessage, 'please try again later');
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

        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    private function truncate(string $value, int $length = 500): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length - 3) . '...';
    }
}
