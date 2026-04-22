<?php

namespace App\Tests;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Service\ProjectDashboardInsightsService;
use PHPUnit\Framework\TestCase;

class ProjectDashboardInsightsServiceTest extends TestCase
{
    public function testBuildClientDashboardReturnsScopedMetrics(): void
    {
        $user = (new User())->setIdUser(7)->setPrenomUser('Nini')->setNomUser('Client')->setRoleUser('client');

        $projectOne = $this->createProject(1, 'Marketplace B2B', 'E-commerce', Project::STATUS_PENDING, 12000, '-1 month', $user);
        $projectTwo = $this->createProject(2, 'Plateforme RH', 'IT/Startups', Project::STATUS_ACCEPTED, 30000, '-2 month', $user);
        $projectThree = $this->createProject(3, 'Studio design', '', Project::STATUS_REFUSED, 0, '-3 month', $user);

        $repository = new InMemoryProjectDashboardRepository([$projectOne, $projectTwo, $projectThree], []);
        $service = new ProjectDashboardInsightsService($repository);

        $dashboard = $service->buildClientDashboard($user, ['status' => Project::STATUS_PENDING, 'type' => 'E-commerce']);

        self::assertSame(3, $dashboard['summary']['total_projects']);
        self::assertSame(1, $dashboard['summary']['pending_projects']);
        self::assertSame(1, $dashboard['summary']['accepted_projects']);
        self::assertSame(1, $dashboard['summary']['refused_projects']);
        self::assertSame(21000.0, $dashboard['summary']['average_budget']);
        self::assertSame([
            ['label' => 'Statut', 'value' => Project::STATUS_PENDING],
            ['label' => 'Type', 'value' => 'E-commerce'],
        ], $dashboard['export_meta']['filters']);
        self::assertSame(['En attente', 'Acceptes', 'Refuses'], $dashboard['charts']['status']['labels']);
        self::assertSame([1, 1, 1], $dashboard['charts']['status']['values']);
        self::assertContains('Non precise', $dashboard['charts']['types']['labels']);
    }

    public function testBuildBackOfficeDashboardUsesBackScopeAndFormatsRole(): void
    {
        $owner = (new User())->setIdUser(9)->setPrenomUser('Chedi')->setNomUser('Ben slima')->setRoleUser('admin');
        $project = $this->createProject(11, 'Hub logistique', 'Transport', Project::STATUS_ACCEPTED, 55000, '-1 month', $owner);

        $repository = new InMemoryProjectDashboardRepository([], [$project]);
        $service = new ProjectDashboardInsightsService($repository);

        $dashboard = $service->buildBackOfficeDashboard(['owner' => 'Chedi', 'q' => 'Hub']);

        self::assertSame('back_office', $dashboard['scope']);
        self::assertSame('Gerant / Admin', $dashboard['export_meta']['role_label']);
        self::assertSame(1, $dashboard['summary']['total_projects']);
        self::assertSame(100.0, $dashboard['summary']['acceptance_rate']);
        self::assertSame([
            ['label' => 'Recherche', 'value' => 'Hub'],
            ['label' => 'Porteur', 'value' => 'Chedi'],
        ], $dashboard['export_meta']['filters']);
    }

    private function createProject(
        int $id,
        string $title,
        string $type,
        string $status,
        float $budget,
        string $createdAtModifier,
        User $owner
    ): Project {
        $project = new Project();
        $project->setIdProj($id);
        $project->setTitle($title);
        $project->setLegacyType($type !== '' ? $type : null);
        $project->setStatus($status);
        $project->setLegacyBudget($budget);
        $project->setStartDate(new \DateTimeImmutable($createdAtModifier));
        $project->setUser($owner);

        return $project;
    }
}

final class InMemoryProjectDashboardRepository extends ProjectRepository
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
