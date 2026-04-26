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

    public function testApprovedStrategyIsRecalculatedWhenProjectChanges(): void
    {
        $controller = new \App\Controller\StrategyController();

        $oldProject = new Project();
        $oldProject->setIdProj(1);
        $oldProject->setBudgetProj(2000);

        $newProject = new Project();
        $newProject->setIdProj(2);
        $newProject->setBudgetProj(900);

        $strategy = new Strategie();
        $strategy->setStatusStrategie(Strategie::STATUS_APPROVED);
        $strategy->setBudgetTotal(1000);
        $strategy->setGainEstime(150);
        $strategy->setProject($newProject);

        self::assertTrue($this->invokePrivate($controller, 'hasStrategyProjectChanged', [$oldProject, $newProject]));

        $this->invokePrivate($controller, 'applyAutomaticStatusRules', [$strategy, Strategie::STATUS_APPROVED, true]);

        self::assertSame(Strategie::STATUS_PENDING, $strategy->getStatusStrategie());
    }

    public function testRejectedStrategyBecomesUnassignedWhenProjectIsRemoved(): void
    {
        $controller = new \App\Controller\StrategyController();

        $oldProject = new Project();
        $oldProject->setIdProj(4);
        $oldProject->setBudgetProj(1800);

        $strategy = new Strategie();
        $strategy->setStatusStrategie(Strategie::STATUS_REJECTED);
        $strategy->setBudgetTotal(1000);
        $strategy->setGainEstime(150);
        $strategy->setProject(null);

        self::assertTrue($this->invokePrivate($controller, 'hasStrategyProjectChanged', [$oldProject, null]));

        $this->invokePrivate($controller, 'applyAutomaticStatusRules', [$strategy, Strategie::STATUS_REJECTED, true]);

        self::assertSame(Strategie::STATUS_UNASSIGNED, $strategy->getStatusStrategie());
    }

    private function invokePrivate(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
