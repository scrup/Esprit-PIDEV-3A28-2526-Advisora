<?php

namespace App\Dto;

final class ProjectAcceptanceEstimate
{
    /**
     * @param array<string, string> $reasons
     */
    public function __construct(
        private readonly int $projectId,
        private readonly int $scorePercent,
        private readonly bool $confidenceLow,
        private readonly string $label,
        private readonly float $tType,
        private readonly float $tClient,
        private readonly float $sBudget,
        private readonly float $sDossier,
        private readonly float $contribType,
        private readonly float $contribClient,
        private readonly float $contribBudget,
        private readonly float $contribDossier,
        private readonly array $reasons,
    ) {
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getScorePercent(): int
    {
        return $this->scorePercent;
    }

    public function isConfidenceLow(): bool
    {
        return $this->confidenceLow;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTType(): float
    {
        return $this->tType;
    }

    public function getTClient(): float
    {
        return $this->tClient;
    }

    public function getSBudget(): float
    {
        return $this->sBudget;
    }

    public function getSDossier(): float
    {
        return $this->sDossier;
    }

    public function getContribType(): float
    {
        return $this->contribType;
    }

    public function getContribClient(): float
    {
        return $this->contribClient;
    }

    public function getContribBudget(): float
    {
        return $this->contribBudget;
    }

    public function getContribDossier(): float
    {
        return $this->contribDossier;
    }

    /**
     * @return array<string, string>
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }
}
