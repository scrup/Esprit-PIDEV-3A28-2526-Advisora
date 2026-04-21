<?php

namespace App\Tests;

use App\Entity\Decision;
use App\Entity\Project;
use App\Service\ProjectSpeechMessageBuilder;
use PHPUnit\Framework\TestCase;

class ProjectSpeechMessageBuilderTest extends TestCase
{
    public function testBuildProjectSummaryContainsKeyProjectFields(): void
    {
        $project = new Project();
        $project->setTitle('Plateforme Advisora');
        $project->setLegacyType('Fintech');
        $project->setLegacyBudget(1500.50);
        $project->setStatus(Project::STATUS_PENDING);
        $project->setStartDate(new \DateTimeImmutable('2026-04-20'));

        $builder = new ProjectSpeechMessageBuilder();
        $message = $builder->buildProjectSummary($project);

        self::assertStringContainsString('Plateforme Advisora', $message);
        self::assertStringContainsString('Fintech', $message);
        self::assertStringContainsString('1 500,50', $message);
        self::assertStringContainsString('20/04/2026', $message);
        self::assertStringContainsString('En attente', $message);
    }

    public function testBuildDecisionAnnouncementForAcceptedProjectCongratulatesClient(): void
    {
        $project = new Project();
        $project->setTitle('Projet Atlas');

        $decision = new Decision();
        $decision->setDecisionTitle(Decision::STATUS_ACTIVE);

        $builder = new ProjectSpeechMessageBuilder();
        $message = $builder->buildDecisionAnnouncement($project, $decision);

        self::assertStringContainsString('Felicitations', $message);
        self::assertStringContainsString('Projet Atlas', $message);
        self::assertStringContainsString('accepte', strtolower($message));
    }

    public function testBuildRefusalReasonIncludesAdminJustification(): void
    {
        $project = new Project();
        $project->setTitle('Projet Orion');

        $decision = new Decision();
        $decision->setDecisionTitle(Decision::STATUS_REFUSED);
        $decision->setDescription('Le budget est insuffisant pour lancer le projet.');

        $builder = new ProjectSpeechMessageBuilder();
        $message = $builder->buildRefusalReason($project, $decision);

        self::assertStringContainsString('Projet Orion', $message);
        self::assertStringContainsString('Le budget est insuffisant', $message);
    }

    public function testBuildSubmissionConfirmationMentionsValidationWaitingState(): void
    {
        $project = new Project();
        $project->setTitle('Projet Nova');

        $builder = new ProjectSpeechMessageBuilder();
        $message = $builder->buildSubmissionConfirmation($project);

        self::assertStringContainsString('Projet Nova', $message);
        self::assertStringContainsString('en attente de validation', strtolower($message));
    }
}
