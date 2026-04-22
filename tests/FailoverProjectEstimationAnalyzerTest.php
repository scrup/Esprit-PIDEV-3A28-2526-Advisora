<?php

namespace App\Tests;

use App\Dto\ProjectEstimationRequest;
use App\Service\FailoverProjectEstimationAnalyzer;
use App\Service\GeminiProjectEstimationService;
use App\Service\OpenAiProjectEstimationService;
use App\Service\ProjectEstimationMetaAwareInterface;
use App\Service\ProjectEstimationProviderException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FailoverProjectEstimationAnalyzerTest extends TestCase
{
    public function testEstimateUsesPrimaryProviderWhenItSucceeds(): void
    {
        $service = new FailoverProjectEstimationAnalyzer(
            new StubOpenAiProjectEstimationService($this->buildEstimationResult(), [
                'provider_used' => 'openai',
                'used_fallback' => false,
                'warning' => null,
                'model' => 'gpt-4o-mini',
            ]),
            new StubGeminiProjectEstimationService($this->buildEstimationResult(), [
                'provider_used' => 'gemini',
                'used_fallback' => false,
                'warning' => null,
                'model' => 'gemini-2.5-flash',
            ]),
            new NullLogger(),
            'openai',
            'gemini'
        );

        $result = $service->estimate($this->createRequest());
        $meta = $service->getLastEstimationMeta();

        self::assertSame('VIABLE', $result['verdict']);
        self::assertSame('openai', $meta['provider_used']);
        self::assertFalse($meta['used_fallback']);
        self::assertNull($meta['warning']);
    }

    public function testEstimateFallsBackToGeminiWhenOpenAiIsRetryable(): void
    {
        $service = new FailoverProjectEstimationAnalyzer(
            new StubOpenAiProjectEstimationService(
                new ProjectEstimationProviderException(
                    'openai',
                    'Le quota ou la facturation OpenAI ne permet pas de generer cette estimation pour le moment.',
                    true,
                    429
                )
            ),
            new StubGeminiProjectEstimationService($this->buildEstimationResult(), [
                'provider_used' => 'gemini',
                'used_fallback' => false,
                'warning' => null,
                'model' => 'gemini-2.5-flash',
            ]),
            new NullLogger(),
            'openai',
            'gemini'
        );

        $result = $service->estimate($this->createRequest());
        $meta = $service->getLastEstimationMeta();

        self::assertSame('VIABLE', $result['verdict']);
        self::assertSame('gemini', $meta['provider_used']);
        self::assertTrue($meta['used_fallback']);
        self::assertStringContainsString('Gemini', $meta['warning'] ?? '');
    }

    public function testEstimateThrowsGenericMessageWhenPrimaryAndFallbackFail(): void
    {
        $service = new FailoverProjectEstimationAnalyzer(
            new StubOpenAiProjectEstimationService(
                new ProjectEstimationProviderException('openai', 'OpenAI rate limit', true, 429)
            ),
            new StubGeminiProjectEstimationService(
                new ProjectEstimationProviderException('gemini', 'Gemini unavailable', true, 503)
            ),
            new NullLogger(),
            'openai',
            'gemini'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Les services d analyse sont temporairement indisponibles.');

        $service->estimate($this->createRequest());
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
    private function buildEstimationResult(): array
    {
        return [
            'verdict' => 'VIABLE',
            'score' => 80,
            'resume' => 'Le projet montre un potentiel solide sur le marche tunisien.',
            'points_forts' => ['Besoin clair', 'Execution structuree', 'Bonne equipe'],
            'points_faibles' => ['Tresorerie a surveiller', 'Distribution a valider', 'Cadre local a cadrer'],
            'recommandations' => ['Valider le marche', 'Consolider la tresorerie', 'Structurer le lancement'],
            'financement_recommande' => [
                'organisme' => 'BFPME',
                'explication' => 'Un accompagnement investissement semble pertinent.',
            ],
            'region_recommandee' => 'Tunis',
            'delai_recommande' => '3 mois',
            'budget_minimum_dt' => 45000.0,
            'probabilite_succes' => 72,
            'startup_act' => [
                'eligible' => false,
                'explication' => 'Le projet n entre pas necessairement dans une logique Startup Act.',
            ],
            'prochaine_etape' => 'Lancer un pilote cible.',
        ];
    }

    private function createRequest(): ProjectEstimationRequest
    {
        $request = new ProjectEstimationRequest();
        $request->projectName = 'Projet test';
        $request->projectType = 'Sante';
        $request->projectDescription = 'Description de test.';
        $request->launchRegion = 'Tunis';
        $request->desiredLaunchDate = new \DateTimeImmutable('+2 months');
        $request->totalBudgetDt = 50000;
        $request->marketingBudgetDt = 5000;
        $request->fundingSource = 'Smart Capital';
        $request->estimatedMonthlyRevenueDt = 12000;
        $request->estimatedProfitabilityDelayMonths = 12;
        $request->teamSize = 4;
        $request->founderExperienceYears = 6;
        $request->teamKeySkills = 'Produit, operations, vente.';
        $request->alreadyLaunchedInTunisia = false;
        $request->targetMarket = 'Grand public (B2C)';
        $request->directCompetitorsTunisia = 3;
        $request->competitiveAdvantage = 'Une meilleure execution.';
        $request->tunisianMarketStudyStatus = 'Oui';
        $request->exportTarget = false;
        $request->mvpStatus = 'En cours';
        $request->mainTechnology = 'Plateforme web';
        $request->plannedLegalStatus = 'SARL';
        $request->needsCertification = 'Non';
        $request->tunisianSpecificRisks = 'Risques de test.';

        return $request;
    }
}

final class StubOpenAiProjectEstimationService extends OpenAiProjectEstimationService
{
    /**
     * @param array<string, mixed>|ProjectEstimationProviderException $result
     * @param array{provider_used: string|null, used_fallback: bool, warning: string|null, model: string|null} $meta
     */
    public function __construct(private array|ProjectEstimationProviderException $result, private array $meta = ['provider_used' => 'openai', 'used_fallback' => false, 'warning' => null, 'model' => 'gpt-4o-mini'])
    {
    }

    public function estimate(ProjectEstimationRequest $request): array
    {
        if ($this->result instanceof ProjectEstimationProviderException) {
            throw $this->result;
        }

        return $this->result;
    }

    public function getLastEstimationMeta(): array
    {
        return $this->meta;
    }
}

final class StubGeminiProjectEstimationService extends GeminiProjectEstimationService
{
    /**
     * @param array<string, mixed>|ProjectEstimationProviderException $result
     * @param array{provider_used: string|null, used_fallback: bool, warning: string|null, model: string|null} $meta
     */
    public function __construct(private array|ProjectEstimationProviderException $result, private array $meta = ['provider_used' => 'gemini', 'used_fallback' => false, 'warning' => null, 'model' => 'gemini-2.5-flash'])
    {
    }

    public function estimate(ProjectEstimationRequest $request): array
    {
        if ($this->result instanceof ProjectEstimationProviderException) {
            throw $this->result;
        }

        return $this->result;
    }

    public function getLastEstimationMeta(): array
    {
        return $this->meta;
    }
}
