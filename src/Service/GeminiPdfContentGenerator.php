<?php

// src/Service/GeminiPdfContentGenerator.php
namespace App\Service;

use App\Entity\Project;
use App\Entity\Strategie;

class GeminiPdfContentGenerator
{
    public function generate(Strategie $strategy, ?Project $project): array
    {
        $estimatedGainAmount = $this->calculateEstimatedGainAmount($strategy);
        $projectBudget = $project?->getBudgetProj();
        $strategyBudget = $strategy->getBudgetTotal();
        $riskMessages = [];

        if ($strategyBudget !== null && $estimatedGainAmount !== null && $estimatedGainAmount < $strategyBudget) {
            $riskMessages[] = sprintf(
                'Le gain estime en montant (%s DT) reste inferieur au budget engage (%s DT).',
                $this->formatAmount($estimatedGainAmount),
                $this->formatAmount($strategyBudget)
            );
        }

        if ($projectBudget !== null && $strategyBudget !== null && $strategyBudget > $projectBudget) {
            $riskMessages[] = sprintf(
                'Le budget de la strategie (%s DT) depasse le budget du projet (%s DT).',
                $this->formatAmount($strategyBudget),
                $this->formatAmount($projectBudget)
            );
        }

        if ($riskMessages === []) {
            $riskMessages[] = 'Aucun risque bloquant n a ete detecte a partir des donnees budgetaires disponibles.';
        }

        $projectTitle = $project?->getTitleProj() ?: 'Aucun projet associe';
        $projectOwner = $project?->getUser()
            ? trim(sprintf('%s %s', (string) $project->getUser()?->getPrenomUser(), (string) $project->getUser()?->getNomUser()))
            : 'Non defini';

        return [
            'executive_summary' => sprintf(
                'La strategie "%s" vise une execution sur %d mois avec un budget de %s DT. Elle est rattachee au projet "%s" et affiche un gain estime de %s.',
                (string) ($strategy->getNomStrategie() ?: 'Strategie sans nom'),
                (int) ($strategy->getDureeTerme() ?? 0),
                $this->formatAmount($strategyBudget),
                $projectTitle,
                $strategy->getGainEstime() !== null ? $strategy->getGainEstime() . '%' : 'Non defini'
            ),
            'highlights' => [
                sprintf('Type de strategie: %s', $strategy->getType() ?: 'Non defini'),
                sprintf('Responsable: %s', $strategy->getUser() ? trim(sprintf('%s %s', (string) $strategy->getUser()?->getPrenomUser(), (string) $strategy->getUser()?->getNomUser())) : 'Non defini'),
                sprintf('Projet support: %s', $projectTitle),
                sprintf('Client du projet: %s', $projectOwner !== '' ? $projectOwner : 'Non defini'),
            ],
            'opportunities' => [
                $estimatedGainAmount !== null
                    ? sprintf('Le gain estime represente environ %s DT sur la base du budget courant.', $this->formatAmount($estimatedGainAmount))
                    : 'Le gain financier estime ne peut pas etre calcule avec les donnees actuelles.',
                $project !== null
                    ? sprintf('Le projet associe dispose d un avancement de %s%% pour accueillir la strategie.', $project->getAvancementProj() !== null ? number_format((float) $project->getAvancementProj(), 0, ',', ' ') : '0')
                    : 'Associer cette strategie a un projet clarifiera la gouvernance et le suivi.',
                'Les objectifs relies peuvent servir de feuille de route immediate pour le lancement du playbook.',
            ],
            'risks' => $riskMessages,
            'actions' => [
                'Valider les hypotheses budgetaires avant lancement.',
                'Confirmer les responsables, livrables et jalons cles.',
                'Suivre les objectifs relies pour piloter la mise en oeuvre.',
                $project !== null ? 'Aligner la strategie avec le calendrier du projet associe.' : 'Associer un projet de reference avant execution.',
            ],
            'kpis' => [
                sprintf('Budget strategie: %s DT', $this->formatAmount($strategyBudget)),
                sprintf('Gain estime: %s', $strategy->getGainEstime() !== null ? $strategy->getGainEstime() . '%' : 'Non defini'),
                sprintf('Nombre d objectifs: %d', $strategy->getObjectives()->count()),
                sprintf('Budget projet: %s', $projectBudget !== null ? $this->formatAmount($projectBudget) . ' DT' : 'Non defini'),
            ],
        ];
    }

    private function calculateEstimatedGainAmount(Strategie $strategy): ?float
    {
        if ($strategy->getBudgetTotal() === null || $strategy->getGainEstime() === null) {
            return null;
        }

        return $strategy->getBudgetTotal() * ($strategy->getGainEstime() / 100);
    }

    private function formatAmount(?float $amount): string
    {
        return $amount !== null ? number_format($amount, 0, ',', ' ') : 'Non defini';
    }
}
