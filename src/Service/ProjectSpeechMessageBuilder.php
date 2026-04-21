<?php

namespace App\Service;

use App\Entity\Decision;
use App\Entity\Project;

class ProjectSpeechMessageBuilder
{
    public function buildProjectSummary(Project $project): string
    {
        $budget = $project->getLegacyBudget() !== null
            ? number_format((float) $project->getLegacyBudget(), 2, ',', ' ') . ' dinars tunisiens'
            : 'non precise';

        $createdAt = $project->getStartDate()?->format('d/m/Y') ?? 'non precisee';
        $type = $project->getLegacyType() ?: 'non precise';

        return $this->normalizeSpeech(
            sprintf(
                'Lecture de la fiche projet. Titre : %s. Type : %s. Budget : %s. Date de creation : %s. Statut actuel : %s.',
                $project->getTitle() ?: 'Projet sans titre',
                $type,
                $budget,
                $createdAt,
                $project->getStatusLabel()
            )
        );
    }

    public function buildRefusalReason(Project $project, Decision $decision): string
    {
        $reason = trim((string) $decision->getDescription());
        if ($reason === '') {
            return $this->normalizeSpeech(
                sprintf(
                    'Le projet %s a ete refuse, mais aucune justification detaillee n a ete fournie.',
                    $project->getTitle() ?: 'sans titre'
                )
            );
        }

        return $this->normalizeSpeech(
            sprintf(
                'Le projet %s a ete refuse. Voici la raison du refus : %s',
                $project->getTitle() ?: 'sans titre',
                $reason
            )
        );
    }

    public function buildSubmissionConfirmation(Project $project): string
    {
        return $this->normalizeSpeech(
            sprintf(
                'Votre projet %s a bien ete soumis. Il est maintenant en attente de validation.',
                $project->getTitle() ?: 'sans titre'
            )
        );
    }

    public function buildDecisionAnnouncement(Project $project, Decision $decision): string
    {
        if ($decision->getDecisionTitle() === Decision::STATUS_ACTIVE) {
            return $this->normalizeSpeech(
                sprintf(
                    'Felicitations, votre projet %s a ete accepte.',
                    $project->getTitle() ?: 'sans titre'
                )
            );
        }

        return $this->buildRefusalReason($project, $decision);
    }

    public function buildNewProjectAlert(Project $project): string
    {
        return $this->normalizeSpeech(
            sprintf(
                'Nouveau projet recu, en attente de votre traitement. Projet : %s.',
                $project->getTitle() ?: 'sans titre'
            )
        );
    }

    private function normalizeSpeech(string $message): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $message));
    }
}
