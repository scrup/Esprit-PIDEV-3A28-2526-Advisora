<?php

namespace App\Tests;

use App\Dto\ProjectEstimationRequest;
use App\Service\OpenAiProjectEstimationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenAiProjectEstimationServiceTest extends TestCase
{
    public function testEstimateReturnsNormalizedStructuredResult(): void
    {
        $service = new OpenAiProjectEstimationService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [
                        [
                            'type' => 'message',
                            'content' => [
                                [
                                    'type' => 'output_text',
                                    'text' => json_encode([
                                        'verdict' => 'VIABLE',
                                        'score' => 82,
                                        'resume' => 'Le projet presente un bon alignement entre besoin local, equipe et traction commerciale en Tunisie.',
                                        'points_forts' => ['Bonne traction locale', 'Equipe experimentee', 'MVP deja avance'],
                                        'points_faibles' => ['Budget marketing serre', 'Concurrence regionale', 'Cadre reglementaire a cadrer'],
                                        'recommandations' => ['Clarifier la distribution', 'Consolider le cash runway', 'Valider les partenaires terrain'],
                                        'financement_recommande' => [
                                            'organisme' => 'Smart Capital',
                                            'explication' => 'Le projet cadre bien avec un accompagnement startup et une logique de croissance.',
                                        ],
                                        'region_recommandee' => 'Tunis',
                                        'delai_recommande' => '3 a 4 mois',
                                        'budget_minimum_dt' => 55000,
                                        'probabilite_succes' => 78,
                                        'startup_act' => [
                                            'eligible' => true,
                                            'explication' => 'Le projet peut viser une eligibility si la composante innovante est bien documentee.',
                                        ],
                                        'prochaine_etape' => 'Finaliser une etude terrain sur les premiers segments payeurs.',
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
            ]),
            new NullLogger(),
            'test-openai-key',
            'https://api.openai.com/v1',
            'gpt-4o-mini'
        );

        $result = $service->estimate($this->createRequest());

        self::assertSame('VIABLE', $result['verdict']);
        self::assertSame(82, $result['score']);
        self::assertSame('Smart Capital', $result['financement_recommande']['organisme']);
        self::assertTrue($result['startup_act']['eligible']);
        self::assertSame(55000.0, $result['budget_minimum_dt']);
    }

    public function testEstimateExtractsJsonWhenResponseContainsExtraText(): void
    {
        $service = new OpenAiProjectEstimationService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output_text' => <<<TEXT
Voici l estimation.
```json
{
  "verdict": "RISQUE",
  "score": 55,
  "resume": "Le projet montre un potentiel reel mais plusieurs verrous de validation locale restent ouverts en Tunisie.",
  "points_forts": ["Besoin marche visible", "Equipe complementaire", "Cible definie"],
  "points_faibles": ["Budget limite", "Distribution a structurer", "Cadre legal a clarifier"],
  "recommandations": ["Mener des entretiens clients", "Securiser des pilotes", "Ajuster le plan de tresorerie"],
  "financement_recommande": {"organisme": "BFPME", "explication": "Le projet doit consolider son plan d investissement avant acceleration."},
  "region_recommandee": "Sousse",
  "delai_recommande": "4 a 6 mois",
  "budget_minimum_dt": 42000,
  "probabilite_succes": 57,
  "startup_act": {"eligible": false, "explication": "Le dossier innovation est encore insuffisamment etaye."},
  "prochaine_etape": "Documenter la proposition de valeur avec des preuves terrain."
}
```
Merci.
TEXT,
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
            ]),
            new NullLogger(),
            'test-openai-key',
            'https://api.openai.com/v1',
            'gpt-4o-mini'
        );

        $result = $service->estimate($this->createRequest());

        self::assertSame('RISQUE', $result['verdict']);
        self::assertSame(55, $result['score']);
        self::assertSame('Sousse', $result['region_recommandee']);
    }

    public function testEstimateThrowsFrenchMessageOnRateLimit(): void
    {
        $service = new OpenAiProjectEstimationService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'error' => [
                        'message' => 'Rate limit exceeded for requests.',
                    ],
                ], JSON_THROW_ON_ERROR), ['http_code' => 429]),
            ]),
            new NullLogger(),
            'test-openai-key',
            'https://api.openai.com/v1',
            'gpt-4o-mini'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le quota ou la facturation OpenAI ne permet pas de generer cette estimation pour le moment.');

        $service->estimate($this->createRequest());
    }

    public function testEstimateThrowsWhenApiKeyIsMissing(): void
    {
        $previousEnv = $_ENV['OPENAI_API_KEY'] ?? null;
        $previousServer = $_SERVER['OPENAI_API_KEY'] ?? null;
        putenv('OPENAI_API_KEY=test-runtime-key');
        $_ENV['OPENAI_API_KEY'] = 'test-runtime-key';
        $_SERVER['OPENAI_API_KEY'] = 'test-runtime-key';

        try {
            $service = new OpenAiProjectEstimationService(
                new MockHttpClient([]),
                new NullLogger(),
                null,
                'https://api.openai.com/v1',
                'gpt-4o-mini'
            );

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('La configuration OpenAI est absente.');

            $service->estimate($this->createRequest());
        } finally {
            if ($previousEnv !== null) {
                $_ENV['OPENAI_API_KEY'] = $previousEnv;
            }

            if ($previousServer !== null) {
                $_SERVER['OPENAI_API_KEY'] = $previousServer;
            }

            if ($previousEnv !== null) {
                putenv('OPENAI_API_KEY=' . $previousEnv);
            } elseif ($previousServer !== null) {
                putenv('OPENAI_API_KEY=' . $previousServer);
            } else {
                putenv('OPENAI_API_KEY');
            }
        }
    }

    private function createRequest(): ProjectEstimationRequest
    {
        $request = new ProjectEstimationRequest();
        $request->projectName = 'SaaS logistique Tunisie';
        $request->projectType = 'IT/Startups';
        $request->projectDescription = 'Une plateforme qui optimise les tournees de livraison pour les PME tunisiennes.';
        $request->launchRegion = 'Tunis';
        $request->desiredLaunchDate = new \DateTimeImmutable('+6 months');
        $request->totalBudgetDt = 65000;
        $request->marketingBudgetDt = 12000;
        $request->fundingSource = 'Smart Capital';
        $request->estimatedMonthlyRevenueDt = 14000;
        $request->estimatedProfitabilityDelayMonths = 16;
        $request->teamSize = 4;
        $request->founderExperienceYears = 7;
        $request->teamKeySkills = 'Produit, data, ventes B2B et operations logistiques.';
        $request->alreadyLaunchedInTunisia = true;
        $request->targetMarket = 'Entreprises (B2B)';
        $request->directCompetitorsTunisia = 5;
        $request->competitiveAdvantage = 'Une meilleure integration terrain avec les contraintes tunisiennes de livraison.';
        $request->tunisianMarketStudyStatus = 'Oui';
        $request->exportTarget = false;
        $request->mvpStatus = 'Oui';
        $request->mainTechnology = 'Symfony et applications mobiles';
        $request->plannedLegalStatus = 'SARL';
        $request->needsCertification = 'Non';
        $request->tunisianSpecificRisks = 'Acces aux devises, recrutement technique et dependance a certains partenaires logistiques.';

        return $request;
    }
}
