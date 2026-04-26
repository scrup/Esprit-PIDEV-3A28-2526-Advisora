<?php

namespace App\Tests;

use App\Controller\ProjectEstimationController;
use App\Dto\ProjectEstimationRequest;
use App\Service\ProjectEstimationAnalyzerInterface;
use App\Service\ProjectEstimationMetaAwareInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ProjectEstimationControllerTest extends KernelTestCase
{
    public function testIndexDisplaysFormOnGet(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ProjectEstimationController::class);
        $request = Request::create('/projects/estimation', 'GET');
        $requestStack = $this->pushRequestWithSession($request);

        try {
            $response = $controller->index($request, new DummyProjectEstimationAnalyzer());
        } finally {
            $requestStack->pop();
        }

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Analyse de lancement de projet sur le marche tunisien', (string) $response->getContent());
    }

    public function testIndexShowsValidationErrorsOnInvalidPost(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ProjectEstimationController::class);
        $request = Request::create('/projects/estimation', 'POST', [
            'wizard_step' => 2,
            'project_estimation' => array_merge($this->createValidFormPayload(), [
                'totalBudgetDt' => '0',
                'marketingBudgetDt' => '1500',
            ]),
        ]);
        $requestStack = $this->pushRequestWithSession($request);
        $request->request->set('project_estimation', array_merge(
            $request->request->all('project_estimation'),
            ['_token' => $this->getCsrfToken()]
        ));

        try {
            $response = $controller->index($request, new DummyProjectEstimationAnalyzer());
        } finally {
            $requestStack->pop();
        }

        $content = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Le budget total doit etre strictement superieur a 0.', $content);
        self::assertStringContainsString('Etape 2 sur 5', $content);
    }

    public function testIndexDisplaysEstimationOnValidPost(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ProjectEstimationController::class);
        $request = Request::create('/projects/estimation', 'POST', [
            'wizard_step' => 5,
            'project_estimation' => $this->createValidFormPayload(),
        ]);
        $requestStack = $this->pushRequestWithSession($request);
        $request->request->set('project_estimation', array_merge(
            $request->request->all('project_estimation'),
            ['_token' => $this->getCsrfToken()]
        ));

        try {
            $response = $controller->index($request, new DummyProjectEstimationAnalyzer());
        } finally {
            $requestStack->pop();
        }

        $content = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Lecture de viabilite pour votre projet', $content);
        self::assertStringContainsString('VIABLE', $content);
        self::assertStringContainsString('Smart Capital', $content);
        self::assertStringContainsString('Potentiellement pertinent', $content);
        self::assertStringContainsString('Analyse via Advisora', $content);
    }

    public function testIndexDisplaysFallbackProviderNoticeWhenMetaIsAvailable(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ProjectEstimationController::class);
        $request = Request::create('/projects/estimation', 'POST', [
            'wizard_step' => 5,
            'project_estimation' => $this->createValidFormPayload(),
        ]);
        $requestStack = $this->pushRequestWithSession($request);
        $request->request->set('project_estimation', array_merge(
            $request->request->all('project_estimation'),
            ['_token' => $this->getCsrfToken()]
        ));

        try {
            $response = $controller->index($request, new FallbackProjectEstimationAnalyzer());
        } finally {
            $requestStack->pop();
        }

        $content = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Analyse via Advisora', $content);
        self::assertStringContainsString('Moteur de secours actif', $content);
        self::assertStringContainsString('Estimation generee via Gemini car OpenAI etait temporairement indisponible.', $content);
    }

    public function testIndexDisplaysUserFacingAnalyzerErrorOnFailure(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ProjectEstimationController::class);
        $request = Request::create('/projects/estimation', 'POST', [
            'wizard_step' => 5,
            'project_estimation' => $this->createValidFormPayload(),
        ]);
        $requestStack = $this->pushRequestWithSession($request);
        $request->request->set('project_estimation', array_merge(
            $request->request->all('project_estimation'),
            ['_token' => $this->getCsrfToken()]
        ));

        try {
            $response = $controller->index($request, new FailingProjectEstimationAnalyzer());
        } finally {
            $requestStack->pop();
        }

        $content = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Le quota ou la facturation OpenAI ne permet pas de generer cette estimation pour le moment.', $content);
    }

    /**
     * @return array<string, string>
     */
    private function createValidFormPayload(): array
    {
        return [
            'projectName' => 'Plateforme healthtech',
            'projectType' => 'Sante',
            'projectDescription' => 'Une solution numerique pour fluidifier la prise de rendez-vous et le suivi patient en Tunisie.',
            'launchRegion' => 'Tunis',
            'desiredLaunchDate' => (new \DateTimeImmutable('+3 months'))->format('Y-m-d'),
            'totalBudgetDt' => '48000',
            'marketingBudgetDt' => '8000',
            'fundingSource' => 'Smart Capital',
            'estimatedMonthlyRevenueDt' => '11000',
            'estimatedProfitabilityDelayMonths' => '14',
            'teamSize' => '4',
            'founderExperienceYears' => '6',
            'teamKeySkills' => 'Produit, sante numerique, operations et ventes.',
            'alreadyLaunchedInTunisia' => '1',
            'targetMarket' => 'Grand public (B2C)',
            'directCompetitorsTunisia' => '3',
            'competitiveAdvantage' => 'Une experience patient plus simple et un meilleur ancrage terrain en Tunisie.',
            'tunisianMarketStudyStatus' => 'Oui',
            'exportTarget' => '0',
            'mvpStatus' => 'Oui',
            'mainTechnology' => 'Symfony et Flutter',
            'plannedLegalStatus' => 'SARL',
            'needsCertification' => 'Je ne sais pas',
            'tunisianSpecificRisks' => 'Adoption digitale, partenariats medicaux et cadre reglementaire a clarifier.',
        ];
    }

    private function getCsrfToken(): string
    {
        /** @var CsrfTokenManagerInterface $csrfTokenManager */
        $csrfTokenManager = self::getContainer()->get('security.csrf.token_manager');

        return $csrfTokenManager->getToken('project_estimation')->getValue();
    }

    private function pushRequestWithSession(Request $request): RequestStack
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        return $requestStack;
    }
}

class DummyProjectEstimationAnalyzer implements ProjectEstimationAnalyzerInterface, ProjectEstimationMetaAwareInterface
{
    public function estimate(ProjectEstimationRequest $request): array
    {
        return [
            'verdict' => 'VIABLE',
            'score' => 81,
            'resume' => 'Le projet est bien positionne pour le marche tunisien avec une execution disciplinee et des validations terrain rapides.',
            'points_forts' => ['Besoin local clair', 'Equipe complementaire', 'Budget coherent'],
            'points_faibles' => ['Distribution a consolider', 'Cadre legal a preciser', 'Dependance a des partenaires clefs'],
            'recommandations' => ['Valider les premiers clients', 'Securiser les partenaires', 'Preparer le dossier financement'],
            'financement_recommande' => [
                'organisme' => 'Smart Capital',
                'explication' => 'Le projet est compatible avec une logique startup structuree et une trajectoire de croissance.',
            ],
            'region_recommandee' => 'Tunis',
            'delai_recommande' => '3 mois',
            'budget_minimum_dt' => 52000.0,
            'probabilite_succes' => 77,
            'startup_act' => [
                'eligible' => true,
                'explication' => 'Le projet peut viser une eligibility si la composante innovante est formalisee.',
            ],
            'prochaine_etape' => 'Lancer un pilote local avec des utilisateurs cibles.',
        ];
    }

    public function getLastEstimationMeta(): array
    {
        return [
            'provider_used' => 'openai',
            'used_fallback' => false,
            'warning' => null,
            'model' => 'gpt-4o-mini',
        ];
    }
}

final class FallbackProjectEstimationAnalyzer extends DummyProjectEstimationAnalyzer
{
    public function getLastEstimationMeta(): array
    {
        return [
            'provider_used' => 'gemini',
            'used_fallback' => true,
            'warning' => 'Estimation generee via Gemini car OpenAI etait temporairement indisponible.',
            'model' => 'gemini-2.5-flash',
        ];
    }
}

final class FailingProjectEstimationAnalyzer implements ProjectEstimationAnalyzerInterface
{
    public function estimate(ProjectEstimationRequest $request): array
    {
        throw new \RuntimeException('Le quota ou la facturation OpenAI ne permet pas de generer cette estimation pour le moment. Verifiez votre projet API et votre solde, puis reessayez.');
    }
}
