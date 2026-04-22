<?php

namespace App\Tests;

use App\Controller\ProjectController;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\DecisionRepository;
use App\Repository\ProjectRepository;
use App\Service\PdfGeneratorService;
use App\Service\ProjectAcceptanceService;
use App\Service\ProjectDashboardInsightsService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProjectControllerDashboardTest extends KernelTestCase
{
    public function testIndexRendersDashboardForClient(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ProjectController::class);
        $request = Request::create('/projects', 'GET');
        $requestStack = $this->pushRequestWithSession($request);
        $this->authenticate('client');

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository
            ->method('findDistinctFrontTypes')
            ->willReturn(['IT/Startups']);

        $acceptanceService = $this->createMock(ProjectAcceptanceService::class);
        $acceptanceService->method('estimateForPending')->willReturn([]);

        $dashboardService = new ProjectDashboardInsightsService(new ControllerDashboardProjectRepository([
            $this->createProject(12, 'Plateforme IA', 'IT/Startups', Project::STATUS_PENDING, 18000.0),
            $this->createProject(13, 'Atelier design', 'Artisanat', Project::STATUS_ACCEPTED, 9000.0),
        ], []));

        try {
            $response = $controller->index($request, $projectRepository, $acceptanceService, $dashboardService);
        } finally {
            $requestStack->pop();
            $this->clearAuthentication();
        }

        $content = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Mon tableau de bord projets', $content);
        self::assertStringContainsString('Exporter PDF', $content);
        self::assertStringContainsString('Repartition par statut', $content);
    }

    public function testBackOverviewRendersGlobalDashboardForAdmin(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ProjectController::class);
        $request = Request::create('/back/projects/overview', 'GET');
        $requestStack = $this->pushRequestWithSession($request);
        $this->authenticate('admin');

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getStatusCounters')->willReturn([
            'PENDING' => 2,
            'ACCEPTED' => 3,
            'REFUSED' => 1,
        ]);
        $projectRepository->method('findLatestProjects')->willReturn([]);

        $decisionRepository = $this->createMock(DecisionRepository::class);
        $decisionRepository->method('count')->willReturn(4);
        $decisionRepository->method('findLatestGlobal')->willReturn([]);

        $dashboardService = new ProjectDashboardInsightsService(new ControllerDashboardProjectRepository([], [
            $this->createProject(31, 'Hub logistique', 'Transport', Project::STATUS_PENDING, 24000.0),
            $this->createProject(32, 'Plateforme supply', 'Transport', Project::STATUS_ACCEPTED, 38000.0),
        ]));

        try {
            $response = $controller->backOverview($projectRepository, $decisionRepository, $dashboardService);
        } finally {
            $requestStack->pop();
            $this->clearAuthentication();
        }

        $content = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Pilotage global des projets', $content);
        self::assertStringContainsString('Exporter PDF', $content);
    }

    public function testExportFrontPdfDownloadsPdfWhenLocalEngineIsAvailable(): void
    {
        self::bootKernel();

        $controller = self::getContainer()->get(ProjectController::class);
        $request = Request::create('/projects/export/pdf', 'GET', ['status' => 'PENDING']);
        $requestStack = $this->pushRequestWithSession($request);
        $this->authenticate('client');

        $dashboardService = new ProjectDashboardInsightsService(new ControllerDashboardProjectRepository([
            $this->createProject(44, 'Marketplace local', 'E-commerce', Project::STATUS_PENDING, 15000.0),
        ], []));

        /** @var \Twig\Environment $twig */
        $twig = self::getContainer()->get('twig');
        $pdfGenerator = new PdfGeneratorService($twig, 'http://127.0.0.1:3000', '0');

        try {
            $response = $controller->exportFrontPdf($request, $dashboardService, $pdfGenerator);
        } finally {
            $requestStack->pop();
            $this->clearAuthentication();
        }

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertNotNull($response->getFile());
        self::assertStringEndsWith('.pdf', $response->getFile()->getFilename());
        self::assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    private function createProject(int $id, string $title, string $type, string $status, float $budget): Project
    {
        $owner = new User();
        $owner->setPrenomUser('Test');
        $owner->setNomUser('Owner');
        $owner->setRoleUser('client');

        $project = new Project();
        $project->setIdProj($id);
        $project->setTitle($title);
        $project->setLegacyType($type);
        $project->setStatus($status);
        $project->setLegacyBudget($budget);
        $project->setStartDate(new \DateTimeImmutable('2026-04-01'));
        $project->setUser($owner);

        return $project;
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

    private function authenticate(string $roleUser): void
    {
        $user = new User();
        $user->setRoleUser($roleUser);
        $user->setPrenomUser('Test');
        $user->setNomUser('User');
        $user->setEmailUser(sprintf('%s@example.test', $roleUser));

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function clearAuthentication(): void
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(null);
    }
}

final class ControllerDashboardProjectRepository extends ProjectRepository
{
    /**
     * @param list<Project> $frontProjects
     * @param list<Project> $backProjects
     */
    public function __construct(private array $frontProjects, private array $backProjects)
    {
    }

    public function findFrontProjects(array $filters = [], ?User $user = null, bool $canSeeAll = false): array
    {
        return $this->frontProjects;
    }

    public function findBackOfficeProjects(array $filters = []): array
    {
        return $this->backProjects;
    }
}
