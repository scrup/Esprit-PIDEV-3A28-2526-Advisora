<?php

namespace App\Tests;

use App\Dto\ProjectEstimationRequest;
use App\Service\GeminiProjectEstimationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GeminiProjectEstimationServiceTest extends TestCase
{
    public function testEstimateReturnsNormalizedStructuredResult(): void
    {
        $service = new GeminiProjectEstimationService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => json_encode([
                                            'verdict' => 'VIABLE',
                                            'score' => 74,
                                            'resume' => 'Le projet dispose d une traction initiale credible et d un ancrage utile sur le marche tunisien.',
                                            'points_forts' => ['Demande locale visible', 'Equipe reactive', 'Positionnement lisible'],
                                            'points_faibles' => ['Execution a structurer', 'Budget marketing limite', 'Dependance a quelques partenaires'],
                                            'recommandations' => ['Valider le canal commercial', 'Clarifier la tresorerie', 'Documenter les contraintes juridiques'],
                                            'financement_recommande' => [
                                                'organisme' => 'BFPME',
                                                'explication' => 'Le projet gagnerait a consolider son plan d investissement avant acceleration.',
                                            ],
                                            'region_recommandee' => 'Sfax',
                                            'delai_recommande' => '2 a 3 mois',
                                            'budget_minimum_dt' => 38000,
                                            'probabilite_succes' => 69,
                                            'startup_act' => [
                                                'eligible' => false,
                                                'explication' => 'Le projet est viable mais ne releve pas necessairement d une logique Startup Act.',
                                            ],
                                            'prochaine_etape' => 'Formaliser une premiere offre commerciale testable sur le terrain.',
                                        ], JSON_THROW_ON_ERROR),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
            ]),
            new NullLogger(),
            'test-gemini-key',
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.5-flash'
        );

        $result = $service->estimate($this->createRequest());

        self::assertSame('VIABLE', $result['verdict']);
        self::assertSame(74, $result['score']);
        self::assertSame('BFPME', $result['financement_recommande']['organisme']);
        self::assertSame('Sfax', $result['region_recommandee']);
    }

    public function testEstimateThrowsWhenApiKeyIsMissing(): void
    {
        $service = new GeminiProjectEstimationService(
            new MockHttpClient([]),
            new NullLogger(),
            null,
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.5-flash'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La configuration Gemini est absente.');

        $service->estimate($this->createRequest());
    }

    private function createRequest(): ProjectEstimationRequest
    {
        $request = new ProjectEstimationRequest();
        $request->projectName = 'Plateforme services Tunisie';
        $request->projectType = 'IT/Startups';
        $request->projectDescription = 'Une solution qui structure la relation entre prestataires et entreprises locales.';
        $request->launchRegion = 'Tunis';
        $request->desiredLaunchDate = new \DateTimeImmutable('+6 months');
        $request->totalBudgetDt = 42000;
        $request->marketingBudgetDt = 7000;
        $request->fundingSource = 'Fonds propres';
        $request->estimatedMonthlyRevenueDt = 10000;
        $request->estimatedProfitabilityDelayMonths = 12;
        $request->teamSize = 3;
        $request->founderExperienceYears = 5;
        $request->teamKeySkills = 'Operations, vente B2B, produit.';
        $request->alreadyLaunchedInTunisia = false;
        $request->targetMarket = 'Entreprises (B2B)';
        $request->directCompetitorsTunisia = 4;
        $request->competitiveAdvantage = 'Une meilleure qualite de service et un suivi plus structure.';
        $request->tunisianMarketStudyStatus = 'Oui';
        $request->exportTarget = false;
        $request->mvpStatus = 'En cours';
        $request->mainTechnology = 'Plateforme web';
        $request->plannedLegalStatus = 'SARL';
        $request->needsCertification = 'Non';
        $request->tunisianSpecificRisks = 'Acquisition de clients et dependance a certains comptes clefs.';

        return $request;
    }
}
