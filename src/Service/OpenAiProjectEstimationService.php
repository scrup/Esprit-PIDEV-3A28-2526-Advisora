<?php

namespace App\Service;

use App\Dto\ProjectEstimationRequest;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAiProjectEstimationService extends AbstractProjectEstimationAnalyzer
{
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private ?string $apiKey = null,
        private ?string $baseUrl = null,
        private ?string $model = null
    ) {
        $this->apiKey = $this->resolveFirstNonEmpty($this->apiKey);
        $this->baseUrl = rtrim(
            $this->resolveFirstNonEmpty($this->baseUrl) ?? self::DEFAULT_BASE_URL,
            '/'
        );
        $this->model = $this->resolveFirstNonEmpty($this->model) ?? self::DEFAULT_MODEL;
    }

    public function estimate(ProjectEstimationRequest $request): array
    {
        $this->resetLastEstimationMeta();

        if ($this->apiKey === null || trim($this->apiKey) === '') {
            $this->logger->error('OpenAI project estimation request aborted because OPENAI_API_KEY is missing.');

            throw new ProjectEstimationProviderException(
                'openai',
                'La configuration OpenAI est absente. Ajoutez PROJECT_ESTIMATION_OPENAI_API_KEY avant de lancer une estimation.',
                false
            );
        }

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $this->buildPayload($request),
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $rawPayload = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->error('OpenAI project estimation transport failure.', [
                'error' => $exception->getMessage(),
            ]);

            throw new ProjectEstimationProviderException(
                'openai',
                'Le service d estimation tunisienne est temporairement indisponible. Merci de reessayer dans quelques instants.',
                true,
                null,
                $exception
            );
        }

        try {
            $payload = $this->decodeApiPayload($rawPayload);
        } catch (\RuntimeException $exception) {
            $this->logger->error('OpenAI project estimation returned an unusable API payload.', [
                'error' => $exception->getMessage(),
            ]);

            throw new ProjectEstimationProviderException(
                'openai',
                'Le service d estimation tunisienne est temporairement indisponible. Merci de reessayer dans quelques instants.',
                true,
                null,
                $exception
            );
        }

        if ($statusCode >= 400 || isset($payload['error'])) {
            $message = $this->extractApiErrorMessage($payload);
            $this->logger->error('OpenAI project estimation returned an error response.', [
                'status_code' => $statusCode,
                'message' => $message,
                'payload' => $payload,
            ]);

            throw new ProjectEstimationProviderException(
                'openai',
                $this->buildUserFacingApiError($statusCode, $message),
                $this->isRetryableApiError($statusCode, $message),
                $statusCode
            );
        }

        $responseText = $this->extractResponseText($payload);

        if ($responseText === '') {
            $this->logger->error('OpenAI project estimation response did not contain usable text.', [
                'payload_keys' => array_keys($payload),
            ]);

            throw new ProjectEstimationProviderException(
                'openai',
                'Le service d estimation a renvoye une reponse incomplete. Merci de reessayer.',
                true
            );
        }

        try {
            $result = $this->normalizeEstimation($this->decodeStructuredResponse($responseText));
        } catch (\RuntimeException $exception) {
            $this->logger->error('OpenAI project estimation response could not be normalized.', [
                'error' => $exception->getMessage(),
            ]);

            throw new ProjectEstimationProviderException(
                'openai',
                'Le service d estimation a renvoye une reponse inexploitable. Merci de reessayer.',
                true,
                null,
                $exception
            );
        }

        $this->recordLastEstimationMeta('openai', $this->model);

        return $result;
    }

    /**
     * Le schema strict evite les reponses bavardes et stabilise le rendu Twig.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(ProjectEstimationRequest $request): array
    {
        return [
            'model' => $this->model,
            'temperature' => 0.2,
            'max_output_tokens' => 1200,
            'instructions' => $this->buildAnalysisInstructions(),
            'input' => $this->buildInputText($request),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'project_estimation_tunisia',
                    'schema' => $this->getResponseSchema(),
                    'strict' => true,
                ],
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
            $this->logger->error('OpenAI project estimation returned invalid API JSON.', [
                'raw_payload' => $rawPayload,
            ]);

            throw new \RuntimeException('La reponse du service d estimation ne peut pas etre interpretee.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('La reponse du service d estimation ne correspond pas au format attendu.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractApiErrorMessage(array $payload): string
    {
        $message = $payload['error']['message'] ?? $payload['message'] ?? 'OpenAI request failed.';

        return is_string($message) ? trim($message) : 'OpenAI request failed.';
    }

    private function buildUserFacingApiError(int $statusCode, string $message): string
    {
        $normalizedMessage = mb_strtolower($message);

        if ($statusCode === 401 || $statusCode === 403) {
            return 'La cle OpenAI configuree est invalide ou n a pas acces a cette fonctionnalite.';
        }

        if (
            $statusCode === 429
            || str_contains($normalizedMessage, 'rate limit')
            || str_contains($normalizedMessage, 'quota')
            || str_contains($normalizedMessage, 'insufficient_quota')
        ) {
            return 'Le quota ou la facturation OpenAI ne permet pas de generer cette estimation pour le moment. Verifiez votre projet API et votre solde, puis reessayez.';
        }

        if ($statusCode >= 500) {
            return 'Le service OpenAI est temporairement indisponible. Merci de reessayer dans quelques instants.';
        }

        return 'Impossible de generer l estimation tunisienne pour le moment. Merci de verifier vos parametres et de reessayer.';
    }

    private function isRetryableApiError(int $statusCode, string $message): bool
    {
        $normalizedMessage = mb_strtolower($message);

        if ($statusCode === 401 || $statusCode === 403) {
            return false;
        }

        return $statusCode === 429
            || $statusCode >= 500
            || str_contains($normalizedMessage, 'rate limit')
            || str_contains($normalizedMessage, 'quota')
            || str_contains($normalizedMessage, 'insufficient_quota')
            || str_contains($normalizedMessage, 'temporarily unavailable')
            || str_contains($normalizedMessage, 'service unavailable')
            || str_contains($normalizedMessage, 'timeout');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractResponseText(array $payload): string
    {
        $topLevel = trim((string) ($payload['output_text'] ?? ''));

        if ($topLevel !== '') {
            return $topLevel;
        }

        foreach (($payload['output'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (!is_array($content)) {
                    continue;
                }

                foreach (['text', 'value'] as $key) {
                    $value = $content[$key] ?? null;

                    if (is_string($value) && trim($value) !== '') {
                        return trim($value);
                    }

                    if (is_array($value) && isset($value['value']) && is_string($value['value']) && trim($value['value']) !== '') {
                        return trim($value['value']);
                    }
                }
            }
        }

        return '';
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
}