<?php

namespace App\Service;

use App\Dto\InvestmentPrediction;
use App\Dto\MacroAnalysis;
use App\Entity\Investment;
use App\Entity\Project;
use App\Repository\ProjectRepository;

final class InvestmentPredictionService
{
    private const TYPE_PRIOR = 20;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly InvestmentSectorResolver $sectorResolver,
        private readonly WorldBankService $worldBankService,
        private readonly MacroRiskEngine $macroRiskEngine,
    ) {
    }

    public function predictForInvestment(Investment $investment): InvestmentPrediction
    {
        $project = $investment->getProject();
        if (!$project instanceof Project) {
            throw new \RuntimeException('Le projet rattache a l investissement est introuvable.');
        }

        return $this->predictForProject($project);
    }

    public function predictForProject(Project $project): InvestmentPrediction
    {
        $macroAnalysis = $this->macroRiskEngine->analyse($this->worldBankService->fetchTunisiaIndicators());

        return $this->buildPrediction(
            $project,
            $this->buildPredictionContext([$project]),
            $macroAnalysis
        );
    }

    /**
     * @param list<Project> $projects
     *
     * @return list<array{
     *     project: Project,
     *     prediction: InvestmentPrediction,
     *     projectedRoi: float,
     *     rankingScore: int,
     *     statusClass: string
     * }>
     */
    public function getTopProjectRecommendations(array $projects, int $limit = 5): array
    {
        $candidates = array_values(array_filter(
            $projects,
            static fn (Project $project): bool => $project->getStatus() !== Project::STATUS_REFUSED
        ));

        if ($candidates === [] || $limit <= 0) {
            return [];
        }

        $macroAnalysis = $this->macroRiskEngine->analyse($this->worldBankService->fetchTunisiaIndicators());
        $context = $this->buildPredictionContext($candidates);
        $recommendations = [];

        foreach ($candidates as $project) {
            $prediction = $this->buildPrediction($project, $context, $macroAnalysis);
            $projectedRoi = $this->computeProjectedRoi($prediction);

            $recommendations[] = [
                'project' => $project,
                'prediction' => $prediction,
                'projectedRoi' => round($projectedRoi, 2),
                'rankingScore' => $this->computeRecommendationRankingScore($prediction, $projectedRoi, $project),
                'statusClass' => $this->resolveProjectStatusClass($project),
            ];
        }

        usort(
            $recommendations,
            static function (array $left, array $right): int {
                $scoreDiff = $right['rankingScore'] <=> $left['rankingScore'];
                if ($scoreDiff !== 0) {
                    return $scoreDiff;
                }

                $roiDiff = $right['projectedRoi'] <=> $left['projectedRoi'];
                if ($roiDiff !== 0) {
                    return $roiDiff;
                }

                $leftProject = $left['project'];
                $rightProject = $right['project'];

                return ($rightProject->getId() ?? 0) <=> ($leftProject->getId() ?? 0);
            }
        );

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * @param list<Project> $projects
     *
     * @return array{
     *     globalRate: float,
     *     statsByType: array<string, array{accepted: int, refused: int, total: int}>,
     *     acceptedBudgetsByType: array<string, list<float>>,
     *     globalMedianBudget: ?float
     * }
     */
    private function buildPredictionContext(array $projects): array
    {
        $globalStats = $this->projectRepository->getHistoricalDecisionStats();
        $globalRate = $globalStats['total'] > 0
            ? ($globalStats['accepted'] / $globalStats['total'])
            : 0.5;

        $allMatchingTypes = [];
        foreach ($projects as $project) {
            $sectorProfile = $this->sectorResolver->resolve(trim((string) $project->getLegacyType()));
            $allMatchingTypes = [...$allMatchingTypes, ...$sectorProfile['matching_types']];
        }

        $allMatchingTypes = array_values(array_filter(array_unique($allMatchingTypes), static fn (string $type): bool => trim($type) !== ''));

        return [
            'globalRate' => $globalRate,
            'statsByType' => $this->projectRepository->getHistoricalDecisionStatsByTypes($allMatchingTypes),
            'acceptedBudgetsByType' => $this->projectRepository->getAcceptedBudgetsByTypes($allMatchingTypes),
            'globalMedianBudget' => $this->median($this->projectRepository->getAcceptedGlobalBudgets()),
        ];
    }

    /**
     * @param array{
     *     globalRate: float,
     *     statsByType: array<string, array{accepted: int, refused: int, total: int}>,
     *     acceptedBudgetsByType: array<string, list<float>>,
     *     globalMedianBudget: ?float
     * } $context
     */
    private function buildPrediction(Project $project, array $context, MacroAnalysis $macroAnalysis): InvestmentPrediction
    {
        $rawProjectType = trim((string) $project->getLegacyType());
        $sectorProfile = $this->sectorResolver->resolve($rawProjectType);
        $matchingTypes = $sectorProfile['matching_types'];

        $sectorStats = $this->sumTypeStats($context['statsByType'], $matchingTypes);
        $acceptanceRate = $this->smoothedRate($sectorStats['accepted'], $sectorStats['total'], self::TYPE_PRIOR, $context['globalRate']);
        $acceptanceRatePercent = (int) round($acceptanceRate * 100);

        $sectorMedianBudget = $this->median($this->mergeBudgetLists($context['acceptedBudgetsByType'], $matchingTypes));
        $budgetFitScore = (int) round($this->computeBudgetScore(
            (float) ($project->getLegacyBudget()),
            $sectorMedianBudget ?? $context['globalMedianBudget']
        ) * 100);

        $projectReadinessScore = (int) round($this->computeProjectReadiness($project) * 100);
        $macroReadinessScore = (int) round($this->computeMacroReadinessScore($macroAnalysis, $sectorProfile));

        $scorePercent = (int) round(
            ($acceptanceRatePercent * 0.35)
            + ($macroReadinessScore * 0.25)
            + ($budgetFitScore * 0.20)
            + ($projectReadinessScore * 0.20)
        );

        [$recommendationLabel, $recommendationBadgeClass] = $this->resolveRecommendation($scorePercent, $macroAnalysis->getAdjustedRoi());

        $similarProjectsCount = max(0, $sectorStats['total']);
        $sectorTitle = $rawProjectType !== '' ? $rawProjectType : $sectorProfile['label'];

        $highlights = [
            sprintf(
                'Secteur %s: %d%% de projets acceptes sur %d reference(s)%s.',
                $sectorProfile['label'],
                $acceptanceRatePercent,
                $similarProjectsCount,
                $similarProjectsCount === 0 ? ' (base globale utilisee)' : ''
            ),
            sprintf(
                'Tunisie: inflation %.2f%%, croissance PIB %.2f%%, taux de credit %.2f%%.',
                $macroAnalysis->getData()->getInflation(),
                $macroAnalysis->getData()->getGdpGrowth(),
                $macroAnalysis->getData()->getLendingRate()
            ),
            sprintf(
                'Budget projet: adequation estimee a %d%% par rapport aux projets similaires deja acceptes.',
                $budgetFitScore
            ),
        ];

        $warnings = [];
        if ($macroAnalysis->getData()->isLendingEstimated()) {
            $warnings[] = sprintf(
                'Le taux de credit tunisien provient du fallback BCT %d (%.2f%%) car World Bank ne renvoie pas de valeur exploitable.',
                $macroAnalysis->getData()->getLendingRateYear(),
                $macroAnalysis->getData()->getLendingRate()
            );
        }

        $reasonBlocks = [
            [
                'title' => 'Macro Tunisie',
                'badge' => $macroReadinessScore . '%',
                'description' => sprintf(
                    '%s. Inflation %.2f%%, PIB %.2f%%, credit %.2f%%. %s',
                    $macroAnalysis->getRiskLevelLabel(),
                    $macroAnalysis->getData()->getInflation(),
                    $macroAnalysis->getData()->getGdpGrowth(),
                    $macroAnalysis->getData()->getLendingRate(),
                    $sectorProfile['outlook']
                ),
                'variant' => $macroAnalysis->getRiskLevelBadgeClass(),
            ],
            [
                'title' => 'Stat secteur',
                'badge' => $acceptanceRatePercent . '%',
                'description' => $similarProjectsCount > 0
                    ? sprintf('%d projet(s) similaires dans l historique, avec une traction sectorielle utile pour la decision.', $similarProjectsCount)
                    : 'Pas assez d historique sectoriel: la base globale des projets est utilisee comme reference.',
                'variant' => $acceptanceRatePercent >= 60 ? 'low' : ($acceptanceRatePercent >= 40 ? 'medium' : 'high'),
            ],
            [
                'title' => 'Budget',
                'badge' => $budgetFitScore . '%',
                'description' => $sectorMedianBudget !== null
                    ? sprintf('Mediane budget acceptee du secteur: %s TND. Le projet reste %s de cette reference.', number_format($sectorMedianBudget, 2, '.', ' '), $budgetFitScore >= 60 ? 'proche' : 'eloigne')
                    : 'Aucune mediane sectorielle disponible: la comparaison se rabat sur la base globale.',
                'variant' => $budgetFitScore >= 60 ? 'low' : ($budgetFitScore >= 40 ? 'medium' : 'high'),
            ],
            [
                'title' => 'Projet',
                'badge' => $projectReadinessScore . '%',
                'description' => sprintf(
                    'Lecture du dossier a partir du titre, de la description, du type et du budget du projet %s.',
                    $projectReadinessScore >= 70 ? 'bien renseigne' : 'encore perfectible'
                ),
                'variant' => $projectReadinessScore >= 70 ? 'low' : ($projectReadinessScore >= 40 ? 'medium' : 'high'),
            ],
        ];

        return new InvestmentPrediction(
            $sectorTitle,
            $sectorProfile['label'],
            $sectorProfile['outlook'],
            $scorePercent,
            $recommendationLabel,
            $recommendationBadgeClass,
            $macroReadinessScore,
            $acceptanceRatePercent,
            $similarProjectsCount,
            $budgetFitScore,
            $projectReadinessScore,
            $sectorMedianBudget,
            $highlights,
            $warnings,
            $reasonBlocks,
            $macroAnalysis,
        );
    }

    /**
     * @param array<string, array{accepted: int, refused: int, total: int}> $statsByType
     * @param list<string> $matchingTypes
     *
     * @return array{accepted: int, refused: int, total: int}
     */
    private function sumTypeStats(array $statsByType, array $matchingTypes): array
    {
        $sum = [
            'accepted' => 0,
            'refused' => 0,
            'total' => 0,
        ];

        foreach ($matchingTypes as $type) {
            $stats = $statsByType[$type] ?? ['accepted' => 0, 'refused' => 0, 'total' => 0];

            $sum['accepted'] += $stats['accepted'];
            $sum['refused'] += $stats['refused'];
        }

        $sum['total'] = $sum['accepted'] + $sum['refused'];

        return $sum;
    }

    /**
     * @param array<string, list<float>> $budgetsByType
     * @param list<string> $matchingTypes
     *
     * @return list<float>
     */
    private function mergeBudgetLists(array $budgetsByType, array $matchingTypes): array
    {
        $merged = [];

        foreach ($matchingTypes as $type) {
            if (!isset($budgetsByType[$type])) {
                continue;
            }

            foreach ($budgetsByType[$type] as $budget) {
                if ($budget > 0) {
                    $merged[] = $budget;
                }
            }
        }

        return $merged;
    }

    private function smoothedRate(int $accepted, int $total, int $prior, float $globalRate): float
    {
        if ($total <= 0) {
            return $this->clamp($globalRate);
        }

        $observedRate = $accepted / max(1, $total);

        return $this->clamp((($observedRate * $total) + ($globalRate * $prior)) / ($total + $prior));
    }

    private function computeBudgetScore(float $budget, ?float $medianBudget): float
    {
        if ($budget <= 0) {
            return 0.2;
        }

        if ($medianBudget === null || $medianBudget <= 0) {
            return 0.5;
        }

        return $this->clamp(exp(-abs(log($budget / $medianBudget))));
    }

    private function computeProjectReadiness(Project $project): float
    {
        $score = 0.0;

        if (trim((string) $project->getTitle()) !== '') {
            $score += 0.25;
        }

        if (mb_strlen(trim((string) $project->getDescription())) >= 30) {
            $score += 0.35;
        }

        if (trim((string) $project->getLegacyType()) !== '') {
            $score += 0.15;
        }

        if ((float) ($project->getLegacyBudget()) > 0) {
            $score += 0.25;
        }

        return $this->clamp($score);
    }

    /**
     * @param array{
     *     macro_bonus: float,
     *     inflation_penalty: float,
     *     lending_penalty: float,
     *     gdp_bonus: float
     * } $sectorProfile
     */
    private function computeMacroReadinessScore(\App\Dto\MacroAnalysis $macroAnalysis, array $sectorProfile): float
    {
        $indicators = $macroAnalysis->getData();

        $macroReadiness = 100.0 - $macroAnalysis->getScore();
        $macroReadiness += (float) $sectorProfile['macro_bonus'];
        $macroReadiness -= max(0.0, $indicators->getInflation() - 4.5) * (float) $sectorProfile['inflation_penalty'];
        $macroReadiness -= max(0.0, $indicators->getLendingRate() - 6.5) * (float) $sectorProfile['lending_penalty'];
        $macroReadiness += max(0.0, $indicators->getGdpGrowth() - 2.0) * (float) $sectorProfile['gdp_bonus'];

        return $this->clamp($macroReadiness, 0.0, 100.0);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRecommendation(int $scorePercent, float $adjustedRoi): array
    {
        if ($scorePercent >= 70 && $adjustedRoi >= 0) {
            return ['Investir', 'positive'];
        }

        if ($scorePercent >= 45) {
            return ['Prudence', 'warning'];
        }

        return ['A eviter', 'danger'];
    }

    private function computeProjectedRoi(InvestmentPrediction $prediction): float
    {
        $baseAdjustedRoi = $prediction->getMacroAnalysis()->getAdjustedRoi();
        $scoreLift = (($prediction->getScorePercent() - 50) / 100) * 4.0;
        $sectorTractionLift = (($prediction->getAcceptanceRatePercent() - 50) / 100) * 2.0;
        $executionLift = (($prediction->getProjectReadinessScore() - 50) / 100) * 1.5;

        return $baseAdjustedRoi + $scoreLift + $sectorTractionLift + $executionLift;
    }

    private function computeRecommendationRankingScore(InvestmentPrediction $prediction, float $projectedRoi, Project $project): int
    {
        $statusBonus = match ($project->getStatus()) {
            Project::STATUS_ACCEPTED => 6,
            Project::STATUS_PENDING => 1,
            default => 0,
        };

        $roiBonus = max(-5.0, min(10.0, $projectedRoi * 2.0));

        return (int) round($prediction->getScorePercent() + $roiBonus + $statusBonus);
    }

    private function resolveProjectStatusClass(Project $project): string
    {
        return match ($project->getStatus()) {
            Project::STATUS_ACCEPTED => 'accepted',
            Project::STATUS_REFUSED => 'refused',
            default => 'pending',
        };
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

    private function clamp(float $value, float $min = 0.0, float $max = 1.0): float
    {
        if (!is_finite($value)) {
            return $min;
        }

        return max($min, min($max, $value));
    }
}
