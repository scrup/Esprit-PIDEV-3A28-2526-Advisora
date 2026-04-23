<?php

namespace App\Dto;

final class InvestmentPrediction
{
    /**
     * @param list<string> $highlights
     * @param list<string> $warnings
     * @param list<array{title: string, badge: string, description: string, variant: string}> $reasonBlocks
     */
    public function __construct(
        private readonly string $projectType,
        private readonly string $sectorLabel,
        private readonly string $sectorOutlook,
        private readonly int $scorePercent,
        private readonly string $recommendationLabel,
        private readonly string $recommendationBadgeClass,
        private readonly int $macroReadinessScore,
        private readonly int $acceptanceRatePercent,
        private readonly int $similarProjectsCount,
        private readonly int $budgetFitScore,
        private readonly int $projectReadinessScore,
        private readonly ?float $medianAcceptedBudget,
        private readonly array $highlights,
        private readonly array $warnings,
        private readonly array $reasonBlocks,
        private readonly MacroAnalysis $macroAnalysis,
    ) {
    }

    public function getProjectType(): string
    {
        return $this->projectType;
    }

    public function getSectorLabel(): string
    {
        return $this->sectorLabel;
    }

    public function getSectorOutlook(): string
    {
        return $this->sectorOutlook;
    }

    public function getScorePercent(): int
    {
        return $this->scorePercent;
    }

    public function getRecommendationLabel(): string
    {
        return $this->recommendationLabel;
    }

    public function getRecommendationBadgeClass(): string
    {
        return $this->recommendationBadgeClass;
    }

    public function getMacroReadinessScore(): int
    {
        return $this->macroReadinessScore;
    }

    public function getAcceptanceRatePercent(): int
    {
        return $this->acceptanceRatePercent;
    }

    public function getSimilarProjectsCount(): int
    {
        return $this->similarProjectsCount;
    }

    public function getBudgetFitScore(): int
    {
        return $this->budgetFitScore;
    }

    public function getProjectReadinessScore(): int
    {
        return $this->projectReadinessScore;
    }

    public function getMedianAcceptedBudget(): ?float
    {
        return $this->medianAcceptedBudget;
    }

    /**
     * @return list<string>
     */
    public function getHighlights(): array
    {
        return $this->highlights;
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return list<array{title: string, badge: string, description: string, variant: string}>
     */
    public function getReasonBlocks(): array
    {
        return $this->reasonBlocks;
    }

    public function getMacroAnalysis(): MacroAnalysis
    {
        return $this->macroAnalysis;
    }
}
