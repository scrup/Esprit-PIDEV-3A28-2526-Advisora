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
        self::assertSame('AcceptÃ©', $decision->getDecisionTitleLabel());

        $decision->setDecisionTitle('rejected');
        self::assertSame(Decision::STATUS_REFUSED, $decision->getDecisionTitle());
        self::assertSame('RefusÃ©', $decision->getDecisionTitleLabel());

        $decision->setDecisionTitle('pending');
        self::assertSame(Decision::STATUS_PENDING, $decision->getDecisionTitle());
        self::assertSame('En attente', $decision->getDecisionTitleLabel());
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

    public function testNewProjectHasSafeFormDefaults(): void
    {
        $project = new Project();

        self::assertSame('', $project->getTitle());
        self::assertSame(0.0, $project->getLegacyBudget());
        self::assertSame(0.0, $project->getAvancementProj());
        self::assertInstanceOf(\DateTimeInterface::class, $project->getStartDate());
        self::assertInstanceOf(\DateTimeInterface::class, $project->getEndDate());
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

    public function testDecisionControllerEnsuresProjectDefaultsWithoutOverwritingExistingValues(): void
    {
        $controller = new DecisionController();

        $project = new Project();
        $project->setStartDate(new \DateTimeImmutable('2026-01-15 10:00:00'));
        $project->setAvancementProj(42.5);
        $project->setStatus(Project::STATUS_ACCEPTED);

        $this->invokePrivate($controller, 'ensureProjectDefaults', [$project]);

        self::assertSame('2026-01-15 10:00:00', $project->getStartDate()?->format('Y-m-d H:i:s'));
        self::assertSame(42.5, $project->getAvancementProj());
        self::assertSame(Project::STATUS_ACCEPTED, $project->getStatus());
    }

    public function testProjectEditPermissionDependsOnRoleAndStatus(): void
    {
        $controller = new ProjectController();

        $owner = new User();
        $owner->setIdUser(10);
        $owner->setRoleUser('client');

        $otherClient = new User();
        $otherClient->setIdUser(11);
        $otherClient->setRoleUser('client');

        $admin = new User();
        $admin->setRoleUser('admin');

        $acceptedProject = new Project();
        $acceptedProject->setUser($owner);
        $acceptedProject->setStatus(Project::STATUS_ACCEPTED);

        $pendingProject = new Project();
        $pendingProject->setUser($owner);
        $pendingProject->setStatus(Project::STATUS_PENDING);

        self::assertTrue($this->invokePrivate($controller, 'canEditProject', [$acceptedProject, $admin]));
        self::assertFalse($this->invokePrivate($controller, 'canEditProject', [$acceptedProject, $owner]));
        self::assertTrue($this->invokePrivate($controller, 'canEditProject', [$pendingProject, $owner]));
        self::assertFalse($this->invokePrivate($controller, 'canEditProject', [$pendingProject, $otherClient]));
    }

    public function testProjectDeletePermissionDependsOnRoleAndProjectStatus(): void
    {
        $controller = new ProjectController();

        $owner = new User();
        $owner->setIdUser(20);
        $owner->setRoleUser('client');

        $admin = new User();
        $admin->setRoleUser('admin');

        $pendingProject = new Project();
        $pendingProject->setUser($owner);
        $pendingProject->setStatus(Project::STATUS_PENDING);

        $refusedProject = new Project();
        $refusedProject->setUser($owner);
        $refusedProject->setStatus(Project::STATUS_REFUSED);

        $acceptedProject = new Project();
        $acceptedProject->setUser($owner);
        $acceptedProject->setStatus(Project::STATUS_ACCEPTED);

        self::assertTrue($this->invokePrivate($controller, 'canDeleteProject', [$pendingProject, $owner]));
        self::assertTrue($this->invokePrivate($controller, 'canDeleteProject', [$refusedProject, $owner]));
        self::assertFalse($this->invokePrivate($controller, 'canDeleteProject', [$acceptedProject, $owner]));
        self::assertTrue($this->invokePrivate($controller, 'canDeleteProject', [$acceptedProject, $admin]));
    }

    public function testTaskStatusPermissionAllowsManagersAndOwningClientOnly(): void
    {
        $controller = new ProjectController();

        $owner = new User();
        $owner->setIdUser(30);
        $owner->setRoleUser('client');

        $otherClient = new User();
        $otherClient->setIdUser(31);
        $otherClient->setRoleUser('client');

        $manager = new User();
        $manager->setRoleUser('gerant');

        $project = new Project();
        $project->setUser($owner);
        $project->setStatus(Project::STATUS_PENDING);

        self::assertTrue($this->invokePrivate($controller, 'canChangeTaskStatus', [$project, $manager]));
        self::assertTrue($this->invokePrivate($controller, 'canChangeTaskStatus', [$project, $owner]));
        self::assertFalse($this->invokePrivate($controller, 'canChangeTaskStatus', [$project, $otherClient]));
        self::assertFalse($this->invokePrivate($controller, 'canChangeTaskStatus', [$project, null]));
    }

    public function testStrategyRiskUsesEstimatedGainAmountInsteadOfRawPercentage(): void
    {
        $controller = new \App\Controller\StrategyController();
        $strategy = new Strategie();
        $strategy->setBudgetTotal(1000);
        $strategy->setGainEstime(80);

        self::assertTrue($this->invokePrivate($controller, 'isStrategyAtRisk', [$strategy]));

        $strategy->setGainEstime(150);

        self::assertFalse($this->invokePrivate($controller, 'isStrategyAtRisk', [$strategy]));
    }

    public function testStrategyRiskDetectsBudgetOverflowAgainstProjectBudget(): void
    {
        $controller = new \App\Controller\StrategyController();
        $project = new Project();
        $project->setBudgetProj(900);

        $strategy = new Strategie();
        $strategy->setBudgetTotal(1000);
        $strategy->setGainEstime(150);
        $strategy->setProject($project);

        self::assertTrue($this->invokePrivate($controller, 'isStrategyAtRisk', [$strategy]));
    }

    public function testRiskyStrategyWithProjectMovesToPending(): void
    {
        $controller = new \App\Controller\StrategyController();
        $project = new Project();
        $project->setBudgetProj(900);

        $strategy = new Strategie();
        $strategy->setBudgetTotal(1000);
        $strategy->setGainEstime(80);
        $strategy->setProject($project);
        $strategy->setStatusStrategie(Strategie::STATUS_UNASSIGNED);

        $this->invokePrivate($controller, 'applyAutomaticStatusRules', [$strategy, Strategie::STATUS_UNASSIGNED]);

        self::assertSame(Strategie::STATUS_PENDING, $strategy->getStatusStrategie());
    }

    public function testNonRiskyStrategyWithProjectMovesToInProgress(): void
    {
        $controller = new \App\Controller\StrategyController();
        $project = new Project();
        $project->setBudgetProj(1500);

        $strategy = new Strategie();
        $strategy->setBudgetTotal(1000);
        $strategy->setGainEstime(150);
        $strategy->setProject($project);
        $strategy->setStatusStrategie(Strategie::STATUS_UNASSIGNED);

        $this->invokePrivate($controller, 'applyAutomaticStatusRules', [$strategy, Strategie::STATUS_UNASSIGNED]);

        self::assertSame(Strategie::STATUS_IN_PROGRESS, $strategy->getStatusStrategie());
    }

    public function testAdminCanDecideOnlyPendingStrategies(): void
    {
        $controller = new \App\Controller\StrategyController();
        $admin = new User();
        $admin->setRoleUser('admin');

        $pendingStrategy = new Strategie();
        $pendingStrategy->setStatusStrategie(Strategie::STATUS_PENDING);

        $approvedStrategy = new Strategie();
        $approvedStrategy->setStatusStrategie(Strategie::STATUS_APPROVED);

        $client = new User();
        $client->setRoleUser('client');

        self::assertTrue($this->invokePrivate($controller, 'canAdminDecideStrategy', [$pendingStrategy, $admin]));
        self::assertFalse($this->invokePrivate($controller, 'canAdminDecideStrategy', [$approvedStrategy, $admin]));
        self::assertFalse($this->invokePrivate($controller, 'canAdminDecideStrategy', [$pendingStrategy, $client]));
    }

    public function testSyncLockedAtSetsApprovalTimestamp(): void
    {
        $controller = new \App\Controller\StrategyController();
        $strategy = new Strategie();
        $strategy->setStatusStrategie(Strategie::STATUS_APPROVED);

        self::assertNull($strategy->getLockedAt());

        $this->invokePrivate($controller, 'syncLockedAtWithStatus', [$strategy, Strategie::STATUS_PENDING]);

        self::assertNotNull($strategy->getLockedAt());
    }

    private function invokePrivate(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

}
