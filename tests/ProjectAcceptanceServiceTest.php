<?php

namespace App\Tests;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Service\ProjectAcceptanceService;
use PHPUnit\Framework\TestCase;

class ProjectAcceptanceServiceTest extends TestCase
{
    public function testNoHistoryKeepsScoreBoundedAndReadable(): void
    {
        $service = $this->makeService();
        $project = $this->makeProject(10, 'FINANCE', 1000, 'Description de projet suffisamment detaillee pour le dossier.');

        $estimate = $service->estimateFor($project);

        self::assertSame(58, $estimate->getScorePercent());
        self::assertSame('Probabilite moyenne', $estimate->getLabel());
        self::assertCount(4, $estimate->getReasons());
        self::assertTrue($estimate->isConfidenceLow());
    }

    public function testFavorableTypeHistoryRaisesTheScore(): void
    {
        $baselineService = $this->makeService();
        $boostedService = $this->makeService([
            'typeStats' => [
                'finance' => ['accepted' => 18, 'refused' => 2, 'total' => 20],
            ],
        ]);

        $project = $this->makeProject(11, 'FINANCE', 1000, 'Description de projet suffisamment detaillee pour le dossier.');

        $baseline = $baselineService->estimateFor($project);
        $boosted = $boostedService->estimateFor($project);

        self::assertGreaterThan($baseline->getScorePercent(), $boosted->getScorePercent());
    }

    public function testBudgetFarFromMedianAppliesVisiblePenalty(): void
    {
        $service = $this->makeService([
            'acceptedBudgetsByType' => [
                'finance' => [100.0, 100.0, 100.0],
            ],
            'globalBudgets' => [100.0, 100.0, 100.0],
        ]);

        $nearBudgetProject = $this->makeProject(12, 'FINANCE', 100, 'Description de projet suffisamment detaillee pour le dossier.');
        $farBudgetProject = $this->makeProject(13, 'FINANCE', 10000, 'Description de projet suffisamment detaillee pour le dossier.');

        $nearEstimate = $service->estimateFor($nearBudgetProject);
        $farEstimate = $service->estimateFor($farBudgetProject);

        self::assertGreaterThan($farEstimate->getScorePercent(), $nearEstimate->getScorePercent());
        self::assertStringContainsString('mediane acceptee', $farEstimate->getReasons()['budget']);
    }

    public function testHistoricallyRefusedClientLowersTheScore(): void
    {
        $neutralService = $this->makeService();
        $penalizedService = $this->makeService([
            'clientStats' => [
                501 => ['accepted' => 0, 'refused' => 10, 'total' => 10],
            ],
        ]);

        $project = $this->makeProject(14, 'FINANCE', 1000, 'Description de projet suffisamment detaillee pour le dossier.', 501);

        $neutral = $neutralService->estimateFor($project);
        $penalized = $penalizedService->estimateFor($project);

        self::assertGreaterThan($penalized->getScorePercent(), $neutral->getScorePercent());
    }

    public function testConfidenceBadgeIsRaisedWhenHistoryIsTooThin(): void
    {
        $service = $this->makeService([
            'typeStats' => [
                'finance' => ['accepted' => 2, 'refused' => 1, 'total' => 3],
            ],
            'clientStats' => [
                777 => ['accepted' => 1, 'refused' => 0, 'total' => 1],
            ],
        ]);

        $project = $this->makeProject(15, 'FINANCE', 1200, 'Description de projet suffisamment detaillee pour le dossier.', 777);

        $estimate = $service->estimateFor($project);

        self::assertTrue($estimate->isConfidenceLow());
    }

    public function testEstimateForPendingReturnsOnlyPendingProjects(): void
    {
        $service = $this->makeService();

        $pendingProject = $this->makeProject(16, 'FINANCE', 1000, 'Description de projet suffisamment detaillee pour le dossier.');
        $acceptedProject = $this->makeProject(17, 'FINANCE', 1000, 'Description de projet suffisamment detaillee pour le dossier.');
        $acceptedProject->setStatus(Project::STATUS_ACCEPTED);

        $estimates = $service->estimateForPending([$pendingProject, $acceptedProject]);

        self::assertCount(1, $estimates);
        self::assertArrayHasKey(16, $estimates);
        self::assertArrayNotHasKey(17, $estimates);
    }

    /**
     * @param array{
     *     historicalStats?: array{accepted: int, refused: int, total: int},
     *     typeStats?: array<string, array{accepted: int, refused: int, total: int}>,
     *     clientStats?: array<int, array{accepted: int, refused: int, total: int}>,
     *     acceptedBudgetsByType?: array<string, list<float>>,
     *     globalBudgets?: list<float>
     * } $overrides
     */
    private function makeService(array $overrides = []): ProjectAcceptanceService
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->method('getHistoricalDecisionStats')->willReturn($overrides['historicalStats'] ?? [
            'accepted' => 0,
            'refused' => 0,
            'total' => 0,
        ]);
        $repository->method('getHistoricalDecisionStatsByTypes')->willReturn($overrides['typeStats'] ?? []);
        $repository->method('getHistoricalDecisionStatsByClients')->willReturn($overrides['clientStats'] ?? []);
        $repository->method('getAcceptedBudgetsByTypes')->willReturn($overrides['acceptedBudgetsByType'] ?? []);
        $repository->method('getAcceptedGlobalBudgets')->willReturn($overrides['globalBudgets'] ?? []);

        return new ProjectAcceptanceService($repository);
    }

    private function makeProject(
        int $id,
        string $type,
        float $budget,
        string $description,
        int $clientId = 101,
    ): Project {
        $project = new Project();
        $project->setIdProj($id);
        $project->setTitle('Projet test');
        $project->setDescription($description);
        $project->setLegacyType($type);
        $project->setLegacyBudget($budget);
        $project->setStatus(Project::STATUS_PENDING);

        $user = new User();
        $user->setIdUser($clientId);
        $project->setUser($user);

        return $project;
    }
}
