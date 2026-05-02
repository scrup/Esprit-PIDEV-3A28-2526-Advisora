<?php

namespace App\Service;

use App\Dto\ProjectAcceptanceEstimate;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use Symfony\Contracts\Cache\CacheInterface;

class ProjectAcceptanceService
{
    private const TYPE_WEIGHT = 0.45;
    private const CLIENT_WEIGHT = 0.20;
    private const BUDGET_WEIGHT = 0.20;
    private const DOSSIER_WEIGHT = 0.15;
    private const TYPE_PRIOR = 30;
    private const CLIENT_PRIOR = 20;
    private const BUDGET_SHAPE = 1.0;

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    public function estimateFor(Project $project): ProjectAcceptanceEstimate
    {
        $estimates = $this->estimateForPending([$project]);
        $projectId = (int) ($project->getId() ?? 0);

        return $estimates[$projectId] ?? $this->buildEstimate(
            $project,
            0.5,
            [],
            [],
            [],
            null,
        );
    }

    /**
     * @param array<int, Project> $pendingProjects
     *
     * @return array<int, ProjectAcceptanceEstimate>
     */
    public function estimateForPending(array $pendingProjects): array
    {
        $pendingProjects = array_values(array_filter(
            $pendingProjects,
            static fn (Project $project): bool => $project->getStatus() === Project::STATUS_PENDING
        ));

        if ($pendingProjects === []) {
            return [];
        }

        $globalStats = $this->projectRepository->getHistoricalDecisionStats();
        $globalRate = $globalStats['total'] > 0 ? $globalStats['accepted'] / $globalStats['total'] : 0.5;

        $normalizedTypes = [];
        $clientIds = [];
        foreach ($pendingProjects as $project) {
            $type = $this->normalizeType($project->getLegacyType());
            if ($type !== '') {
                $normalizedTypes[] = $type;
            }

            $clientId = (int) ($project->getUser()?->getIdUser() ?? 0);
            if ($clientId > 0) {
                $clientIds[] = $clientId;
            }
        }

        $typeStats = $this->projectRepository->getHistoricalDecisionStatsByTypes($normalizedTypes);
        $clientStats = $this->projectRepository->getHistoricalDecisionStatsByClients($clientIds);
        $acceptedBudgetsByType = $this->getCachedAcceptedBudgetsByTypes($normalizedTypes);
        $globalMedianBudget = $this->getCachedGlobalMedianBudget();

        $estimates = [];
        foreach ($pendingProjects as $project) {
            $projectId = (int) ($project->getId() ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $estimates[$projectId] = $this->buildEstimate(
                $project,
                $globalRate,
                $typeStats,
                $clientStats,
                $acceptedBudgetsByType,
                $globalMedianBudget,
            );
        }

        return $estimates;
    }

    /**
     * @param array<string, array{accepted: int, refused: int, total: int}> $typeStats
     * @param array<int, array{accepted: int, refused: int, total: int}> $clientStats
     * @param array<string, list<float>> $acceptedBudgetsByType
     */
    private function buildEstimate(
        Project $project,
        float $globalRate,
        array $typeStats,
        array $clientStats,
        array $acceptedBudgetsByType,
        ?float $globalMedianBudget,
    ): ProjectAcceptanceEstimate {
        $normalizedType = $this->normalizeType($project->getLegacyType());
        $typeStat = $typeStats[$normalizedType] ?? ['accepted' => 0, 'refused' => 0, 'total' => 0];
        $clientId = (int) ($project->getUser()?->getIdUser() ?? 0);
        $clientStat = $clientStats[$clientId] ?? ['accepted' => 0, 'refused' => 0, 'total' => 0];

        $tType = $this->smoothedRate($typeStat['accepted'], $typeStat['total'], self::TYPE_PRIOR, $globalRate);
        $tClient = $this->smoothedRate($clientStat['accepted'], $clientStat['total'], self::CLIENT_PRIOR, $globalRate);

        $typeMedianBudget = $this->median($acceptedBudgetsByType[$normalizedType] ?? []);
        $budget = (float) ($project->getLegacyBudget());
        $sBudget = $this->computeBudgetScore($budget, $typeMedianBudget ?? $globalMedianBudget);
        $dossierScore = $this->computeDossierScore($project);

        $contribType = self::TYPE_WEIGHT * $tType;
        $contribClient = self::CLIENT_WEIGHT * $tClient;
        $contribBudget = self::BUDGET_WEIGHT * $sBudget;
        $contribDossier = self::DOSSIER_WEIGHT * $dossierScore;

        $score = $this->clamp($contribType + $contribClient + $contribBudget + $contribDossier);
        $scorePercent = (int) round($score * 100);

        return new ProjectAcceptanceEstimate(
            (int) ($project->getId() ?? 0),
            $scorePercent,
            $typeStat['total'] < 5 && $clientStat['total'] < 3,
            $this->resolveLabel($scorePercent),
            $tType,
            $tClient,
            $sBudget,
            $dossierScore,
            $contribType,
            $contribClient,
            $contribBudget,
            $contribDossier,
            [
                'type' => $this->buildTypeReason($typeStat, $globalRate),
                'client' => $this->buildClientReason($clientStat, $globalRate),
                'budget' => $this->buildBudgetReason($budget, $typeMedianBudget ?? $globalMedianBudget, $sBudget),
                'dossier' => $this->buildDossierReason($project, $dossierScore),
            ],
        );
    }

    private function computeDossierScore(Project $project): float
    {
        $score = 0.0;

        if (trim((string) $project->getTitle()) !== '') {
            $score += 0.25;
        }

        if (mb_strlen(trim((string) $project->getDescription())) >= 30) {
            $score += 0.35;
        }

        if ($this->normalizeType($project->getLegacyType()) !== '') {
            $score += 0.15;
        }

        if ((float) ($project->getLegacyBudget()) > 0) {
            $score += 0.25;
        }

        return $this->clamp($score);
    }

    private function computeBudgetScore(float $budget, ?float $median): float
    {
        if ($budget <= 0) {
            return 0.2;
        }

        if ($median === null || $median <= 0) {
            return 0.5;
        }

        $score = exp(-abs(log($budget / $median)) / self::BUDGET_SHAPE);

        return $this->clamp($score);
    }

    private function smoothedRate(int $accepted, int $total, int $prior, float $globalRate): float
    {
        if ($total <= 0) {
            return $this->clamp($globalRate);
        }

        $rate = (($total * ($accepted / $total)) + ($prior * $globalRate)) / ($total + $prior);

        return $this->clamp($rate);
    }

    /**
     * @param list<float> $values
     */
    private function median(array $values): ?float
    {
        $values = array_values(array_filter($values, static fn (float|int $value): bool => (float) $value > 0));
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    private function resolveLabel(int $scorePercent): string
    {
        return match (true) {
            $scorePercent >= 70 => 'Bonne probabilite',
            $scorePercent >= 40 => 'Probabilite moyenne',
            default => 'Probabilite faible',
        };
    }

    /**
     * @param array{accepted: int, refused: int, total: int} $typeStat
     */
    private function buildTypeReason(array $typeStat, float $globalRate): string
    {
        if ($typeStat['total'] <= 0) {
            return sprintf('Peu de donnees. Base globale: %d%%.', (int) round($globalRate * 100));
        }

        return sprintf(
            '%d%% acceptes sur %d projet(s) similaires.',
            (int) round(($typeStat['accepted'] / max(1, $typeStat['total'])) * 100),
            $typeStat['total'],
        );
    }

    /**
     * @param array{accepted: int, refused: int, total: int} $clientStat
     */
    private function buildClientReason(array $clientStat, float $globalRate): string
    {
        if ($clientStat['total'] <= 0) {
            return sprintf('Pas d historique client. Base globale: %d%%.', (int) round($globalRate * 100));
        }

        return sprintf(
            '%d%% acceptes sur %d projet(s) du client.',
            (int) round(($clientStat['accepted'] / max(1, $clientStat['total'])) * 100),
            $clientStat['total'],
        );
    }

    private function buildBudgetReason(float $budget, ?float $median, float $score): string
    {
        if ($budget <= 0) {
            return 'Budget invalide.';
        }

        if ($median === null || $median <= 0) {
            return 'Pas de reference budget.';
        }

        return sprintf(
            'Proche de la mediane acceptee (%s TND): %d%%.',
            number_format($median, 2, '.', ' '),
            (int) round($score * 100),
        );
    }

    private function buildDossierReason(Project $project, float $score): string
    {
        $criteria = 0;

        if (trim((string) $project->getTitle()) !== '') {
            ++$criteria;
        }

        if (mb_strlen(trim((string) $project->getDescription())) >= 30) {
            ++$criteria;
        }

        if ($this->normalizeType($project->getLegacyType()) !== '') {
            ++$criteria;
        }

        if ((float) ($project->getLegacyBudget()) > 0) {
            ++$criteria;
        }

        return sprintf(
            '%d/4 points complets. Qualite: %d%%.',
            $criteria,
            (int) round($score * 100),
        );
    }

    /**
     * @return ?float
     */
    private function getCachedGlobalMedianBudget(): ?float
    {
        $cacheKey = 'project_acceptance.globalMedianBudget.v1';

        return $this->cache->get($cacheKey, function (\Psr\Cache\CacheItemInterface $item): ?float {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            $budgets = $this->projectRepository->getAcceptedGlobalBudgets();

            return $this->median($budgets);
        });
    }

    /**
     * @param list<string> $normalizedTypes
     *
     * @return array<string, list<float>>
     */
    private function getCachedAcceptedBudgetsByTypes(array $normalizedTypes): array
    {
        $normalizedTypes = array_values(array_unique(array_map(
            static fn (string $type): string => mb_strtolower(trim($type)),
            $normalizedTypes
        )));

        sort($normalizedTypes, SORT_STRING);

        $cacheKey = 'project_acceptance_acceptedBudgetsByTypes_v1_' . md5(implode('|', $normalizedTypes));

        /** @var array<string, list<float>> $value */
        $value = $this->cache->get($cacheKey, function (\Psr\Cache\CacheItemInterface $item) use ($normalizedTypes): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->projectRepository->getAcceptedBudgetsByTypes($normalizedTypes);
        });

        return $value;
    }

    private function normalizeType(?string $type): string
    {
        return mb_strtolower(trim((string) $type));
    }

    private function clamp(float $value): float
    {
        if (!is_finite($value)) {
            return 0.0;
        }

        return max(0.0, min(1.0, $value));
    }
}
