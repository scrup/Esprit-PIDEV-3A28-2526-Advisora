<?php

namespace App\Tests;

use App\Controller\DecisionController;
use App\Controller\ProjectController;
use App\Entity\Decision;
use App\Entity\Investment;
use App\Entity\Project;
use App\Entity\Strategie;
use App\Entity\Task;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ProjectDecisionModuleTest extends TestCase
{
    public function testDecisionTitleNormalizationUsesLegacyDatabaseValues(): void
    {
        $decision = new Decision();

        $decision->setDecisionTitle('accepted');
        self::assertSame(Decision::STATUS_ACTIVE, $decision->getDecisionTitle());
        self::assertSame('Accepté', $decision->getDecisionTitleLabel());

        $decision->setDecisionTitle('rejected');
        self::assertSame(Decision::STATUS_REFUSED, $decision->getDecisionTitle());
        self::assertSame('Refusé', $decision->getDecisionTitleLabel());

        $decision->setDecisionTitle('pending');
        self::assertSame(Decision::STATUS_PENDING, $decision->getDecisionTitle());
        self::assertSame('En attente', $decision->getDecisionTitleLabel());
    }

    public function testProjectStartDateStaysNullUntilExplicitlySet(): void
    {
        $project = new Project();

        self::assertNull($project->getStartDate());

        $date = new \DateTimeImmutable('2026-04-07 09:30:00');
        $project->setStartDate($date);

        self::assertSame($date, $project->getStartDate());
    }

    public function testClientEditNormalizationResetsStatusToPendingAndKeepsTechnicalDates(): void
    {
        $controller = new ProjectController();
        $project = new Project();
        $project->setTitle('Projet test');
        $project->setStatus(Project::STATUS_ACCEPTED);

        $user = new User();
        $user->setRoleUser('client');

        $this->invokePrivate($controller, 'normalizeProjectForPersistence', [$project, $user]);

        self::assertSame(Project::STATUS_PENDING, $project->getStatus());
        self::assertNotNull($project->getStartDate());
        self::assertNull($project->getEndDate());
        self::assertSame(0.01, $project->getLegacyBudget());
        self::assertSame(0.0, $project->getAvancementProj());
    }

    public function testProjectDeletionIsBlockedWhenSensitiveDependenciesExist(): void
    {
        $controller = new ProjectController();

        $emptyProject = new Project();
        self::assertFalse($this->invokePrivate($controller, 'hasBlockingProjectDependencies', [$emptyProject]));

        $projectWithRelations = new Project();
        $projectWithRelations->addInvestment(new Investment());
        $projectWithRelations->addStrategie(new Strategie());
        $projectWithRelations->addTask(new Task());

        self::assertTrue($this->invokePrivate($controller, 'hasBlockingProjectDependencies', [$projectWithRelations]));
    }

    public function testDecisionControllerMapsLegacyDecisionValuesToProjectStatus(): void
    {
        $controller = new DecisionController();
        $project = new Project();
        $decision = new Decision();

        $decision->setDecisionTitle(Decision::STATUS_ACTIVE);
        $this->invokePrivate($controller, 'syncProjectStatusFromDecision', [$project, $decision]);
        self::assertSame(Project::STATUS_ACCEPTED, $project->getStatus());

        $decision->setDecisionTitle(Decision::STATUS_REFUSED);
        $this->invokePrivate($controller, 'syncProjectStatusFromDecision', [$project, $decision]);
        self::assertSame(Project::STATUS_REFUSED, $project->getStatus());

        $decision->setDecisionTitle(Decision::STATUS_PENDING);
        $this->invokePrivate($controller, 'syncProjectStatusFromDecision', [$project, $decision]);
        self::assertSame(Project::STATUS_PENDING, $project->getStatus());
    }

    private function invokePrivate(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
