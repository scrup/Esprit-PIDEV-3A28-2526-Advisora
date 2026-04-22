<?php

namespace App\Service;

use App\Dto\ProjectEstimationRequest;
use Psr\Log\LoggerInterface;

final class FailoverProjectEstimationAnalyzer extends AbstractProjectEstimationAnalyzer
{
    public function __construct(
        private OpenAiProjectEstimationService $openAiAnalyzer,
        private GeminiProjectEstimationService $geminiAnalyzer,
        private LoggerInterface $logger,
        private ?string $primaryProvider = 'openai',
        private ?string $fallbackProvider = 'gemini'
    ) {
    }

    public function estimate(ProjectEstimationRequest $request): array
    {
        $this->resetLastEstimationMeta();

        $primaryProvider = $this->normalizeProviderName($this->primaryProvider) ?? 'openai';
        $fallbackProvider = $this->normalizeProviderName($this->fallbackProvider);

        try {
            return $this->estimateWithProvider($primaryProvider, $request, false, null);
        } catch (ProjectEstimationProviderException $primaryException) {
            $this->logger->warning('Primary project estimation provider failed.', [
                'provider' => $primaryProvider,
                'retryable' => $primaryException->isRetryable(),
                'status_code' => $primaryException->getStatusCode(),
                'message' => $primaryException->getMessage(),
            ]);

            if (
                !$primaryException->isRetryable()
                || $fallbackProvider === null
                || $fallbackProvider === $primaryProvider
            ) {
                throw $primaryException;
            }

            try {
                $warning = sprintf(
                    'Estimation generee via %s car %s etait temporairement indisponible.',
                    $this->buildProviderLabel($fallbackProvider),
                    $this->buildProviderLabel($primaryProvider)
                );

                return $this->estimateWithProvider($fallbackProvider, $request, true, $warning);
            } catch (ProjectEstimationProviderException $fallbackException) {
                $this->logger->error('Fallback project estimation provider failed.', [
                    'provider' => $fallbackProvider,
                    'retryable' => $fallbackException->isRetryable(),
                    'status_code' => $fallbackException->getStatusCode(),
                    'message' => $fallbackException->getMessage(),
                ]);

                throw new \RuntimeException(
                    'Les services d analyse sont temporairement indisponibles. Merci de reessayer dans quelques instants.',
                    0,
                    $fallbackException
                );
            }
        }
    }

    /**
     * @return array{
     *     verdict: string,
     *     score: int,
     *     resume: string,
     *     points_forts: array<int, string>,
     *     points_faibles: array<int, string>,
     *     recommandations: array<int, string>,
     *     financement_recommande: array{organisme: string, explication: string},
     *     region_recommandee: string,
     *     delai_recommande: string,
     *     budget_minimum_dt: float,
     *     probabilite_succes: int,
     *     startup_act: array{eligible: bool, explication: string},
     *     prochaine_etape: string
     * }
     */
    private function estimateWithProvider(
        string $provider,
        ProjectEstimationRequest $request,
        bool $usedFallback,
        ?string $warning
    ): array {
        $analyzer = $this->resolveAnalyzer($provider);
        if ($analyzer === null) {
            throw new ProjectEstimationProviderException(
                $provider,
                'Le fournisseur d estimation configure est inconnu.',
                false
            );
        }

        $result = $analyzer->estimate($request);
        $meta = $analyzer instanceof ProjectEstimationMetaAwareInterface
            ? $analyzer->getLastEstimationMeta()
            : ['provider_used' => $provider, 'used_fallback' => false, 'warning' => null, 'model' => null];

        $this->recordLastEstimationMeta(
            $provider,
            $meta['model'] ?? null,
            $usedFallback,
            $warning ?? ($meta['warning'] ?? null)
        );

        return $result;
    }

    private function resolveAnalyzer(string $provider): ?ProjectEstimationAnalyzerInterface
    {
        return match ($provider) {
            'openai' => $this->openAiAnalyzer,
            'gemini' => $this->geminiAnalyzer,
            default => null,
        };
    }

    private function normalizeProviderName(?string $provider): ?string
    {
        if ($provider === null) {
            return null;
        }

        $normalizedProvider = trim(mb_strtolower($provider));

        return $normalizedProvider !== '' ? $normalizedProvider : null;
    }

    private function buildProviderLabel(string $provider): string
    {
        return match ($provider) {
            'openai' => 'OpenAI',
            'gemini' => 'Gemini',
            default => ucfirst($provider),
        };
    }
}
