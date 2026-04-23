<?php

namespace App\Service;

use App\Entity\Decision;
use App\Entity\Project;

class ProjectSpeechMessageBuilder
{
    public function buildProjectSummary(Project $project): string
    {
        $parts = [
            sprintf('Projet %s.', $this->sanitize($project->getTitle()) ?: 'sans titre'),
            sprintf('Statut %s.', $this->sanitize($project->getStatusLabel()) ?: 'non precise'),
        ];

        $type = $this->sanitize($project->getLegacyType());
        if ($type !== '') {
            $parts[] = sprintf('Type %s.', $type);
        }

        $budget = $this->formatBudget($project->getLegacyBudget());
        if ($budget !== '') {
            $parts[] = sprintf('Budget %s.', $budget);
        }

        if ($project->getStartDate() instanceof \DateTimeInterface) {
            $parts[] = sprintf('Cree le %s.', $project->getStartDate()->format('d/m/Y'));
        }

        return $this->normalizeSpeech(implode(' ', $parts));
    }

    public function buildRefusalReason(Project $project, Decision $decision): string
    {
        $title = $this->sanitize($project->getTitle()) ?: 'sans titre';
        $reason = $this->sanitize($decision->getDescription());

        if ($reason === '') {
            return $this->normalizeSpeech(sprintf(
                'Le projet %s a ete refuse, mais aucune justification detaillee n a ete fournie.',
                $title
            ));
        }

        return $this->normalizeSpeech(sprintf(
            'Le projet %s a ete refuse. Raison du refus : %s',
            $title,
            $reason
        ));
    }

    public function buildDecisionAnnouncement(Project $project, Decision $decision): string
    {
        $title = $this->sanitize($project->getTitle()) ?: 'sans titre';

        if ($decision->getDecisionTitle() === Decision::STATUS_ACTIVE) {
            return $this->normalizeSpeech(sprintf(
                'Nouvelle decision pour votre projet %s. Bonne nouvelle, il a ete accepte.',
                $title
            ));
        }

        if ($decision->getDecisionTitle() === Decision::STATUS_REFUSED) {
            $reason = $this->sanitize($decision->getDescription());

            if ($reason !== '') {
                return $this->normalizeSpeech(sprintf(
                    'Nouvelle decision pour votre projet %s. Il a ete refuse. Motif : %s',
                    $title,
                    $reason
                ));
            }

            return $this->normalizeSpeech(sprintf(
                'Nouvelle decision pour votre projet %s. Il a ete refuse.',
                $title
            ));
        }

        return $this->normalizeSpeech(sprintf(
            'Une nouvelle decision a ete enregistree pour votre projet %s.',
            $title
        ));
    }

    private function formatBudget(?float $budget): string
    {
        if ($budget === null || $budget <= 0) {
            return '';
        }

        return number_format($budget, 2, ',', ' ') . ' dinars tunisiens';
    }

    private function sanitize(?string $value): string
    {
        $plain = trim(strip_tags((string) $value));

        return (string) preg_replace('/\s+/', ' ', $plain);
    }

    private function normalizeSpeech(string $message): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $message));
    }
}
