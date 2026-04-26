<?php

namespace App\Service;

use App\Entity\Objective;
use App\Entity\Project;
use App\Entity\Strategie;

class StrategyPlaybookLocalizationService
{
    private const DEFAULT_LANGUAGE = 'fr';

    /**
     * @var list<string>
     */
    private const SUPPORTED_LANGUAGES = ['fr', 'en'];

    public function __construct(
        private LibreTranslateService $translator
    ) {
    }

    public function normalizeLanguage(?string $language): string
    {
        $language = strtolower(trim((string) $language));

        return in_array($language, self::SUPPORTED_LANGUAGES, true) ? $language : self::DEFAULT_LANGUAGE;
    }

    /**
     * @param array<string, mixed> $content
     * @param list<string> $messages
     *
     * @return array{
     *     language: string,
     *     labels: array<string, mixed>,
     *     content: array<string, mixed>,
     *     strategy: array<string, string>,
     *     project: array<string, string>|null,
     *     objectives: list<array{name: string, description: string, priority_label: string, priority_badge: string}>,
     *     messages: list<string>
     * }
     */
    public function buildViewModel(
        string $language,
        Strategie $strategy,
        ?Project $project,
        array $content,
        array $messages = []
    ): array {
        $language = $this->normalizeLanguage($language);
        $labels = $this->getLabels($language);

        return [
            'language' => $language,
            'labels' => $labels,
            'content' => $this->localizeContent($content, $language),
            'strategy' => $this->localizeStrategy($strategy, $language, $labels),
            'project' => $this->localizeProject($project, $language, $labels),
            'objectives' => $this->localizeObjectives($strategy, $language, $labels),
            'messages' => $this->localizeMessages($messages, $language),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabels(string $language): array
    {
        $language = $this->normalizeLanguage($language);

        if ($language === 'en') {
            return [
                'document_title' => 'Strategy Playbook',
                'eyebrow' => 'Strategy Playbook',
                'associated_project' => 'Associated project',
                'strategy_budget' => 'Strategy budget',
                'estimated_gain' => 'Estimated gain',
                'target_duration' => 'Target duration',
                'strategy_owner' => 'Strategy owner',
                'linked_objectives_count' => 'Linked objectives',
                'project_budget' => 'Project budget',
                'project_progress' => 'Project progress',
                'type' => 'Type',
                'status' => 'Status',
                'project_client' => 'Project client',
                'generated_on' => 'Generated on',
                'generated_from' => 'from Advisora.',
                'context_and_signals' => 'Context and signals',
                'project_context' => 'Project context',
                'strategic_context' => 'Strategic context',
                'justification' => 'Justification',
                'no_project_description' => 'No project description available.',
                'no_strategic_context' => 'No strategic context note provided.',
                'strategic_diagnosis' => 'Strategic diagnosis',
                'key_points' => 'Key points',
                'strategic_priorities' => 'Strategic priorities',
                'linked_objectives' => 'Linked objectives',
                'objective_fallback' => 'Objective',
                'objective_priority_pattern' => 'Priority %s',
                'no_description' => 'No description provided.',
                'opportunities_and_value' => 'Opportunities and value creation',
                'expected_outcome_projection' => 'Expected outcome projection',
                'projected_start' => 'Projected start',
                'final_projection' => 'Final projection',
                'execution_roadmap' => 'Execution roadmap',
                'risks_and_watchouts' => 'Risks and watch points',
                'recommended_countermeasures' => 'Recommended countermeasures',
                'recommended_action_plan' => 'Recommended action plan',
                'steering_indicators' => 'Steering indicators',
                'cadence' => 'Cadence',
                'duration_unit' => 'months',
                'undefined' => 'Not defined',
                'no_project' => 'No project',
                'strategy_without_name' => 'Untitled strategy',
                'messages' => [
                    'forbidden' => 'You are not allowed to generate this strategy playbook.',
                    'only_approved' => 'Only approved strategies can be exported as a playbook.',
                    'pdf_disabled' => 'PDF generation is disabled. A printable HTML version has been prepared. Open it and use Print > Save as PDF.',
                    'pdf_unavailable' => 'The PDF service is unavailable. A printable HTML version has been prepared. Open it and use Print > Save as PDF.',
                ],
            ];
        }

        return [
            'document_title' => 'Playbook Strategie',
            'eyebrow' => 'Playbook strategie',
            'associated_project' => 'Projet associe',
            'strategy_budget' => 'Budget strategie',
            'estimated_gain' => 'Gain estime',
            'target_duration' => 'Duree cible',
            'strategy_owner' => 'Responsable strategie',
            'linked_objectives_count' => 'Objectifs relies',
            'project_budget' => 'Budget projet',
            'project_progress' => 'Avancement projet',
            'type' => 'Type',
            'status' => 'Statut',
            'project_client' => 'Client projet',
            'generated_on' => 'Document genere le',
            'generated_from' => 'depuis Advisora.',
            'context_and_signals' => 'Contexte et signaux',
            'project_context' => 'Contexte projet',
            'strategic_context' => 'Contexte strategique',
            'justification' => 'Justification',
            'no_project_description' => 'Aucune description projet disponible.',
            'no_strategic_context' => 'Aucune note de contexte strategique fournie.',
            'strategic_diagnosis' => 'Diagnostic strategique',
            'key_points' => 'Points cles',
            'strategic_priorities' => 'Priorites strategiques',
            'linked_objectives' => 'Objectifs rattaches',
            'objective_fallback' => 'Objectif',
            'objective_priority_pattern' => 'Priorite %s',
            'no_description' => 'Aucune description fournie.',
            'opportunities_and_value' => 'Opportunites et creation de valeur',
            'expected_outcome_projection' => 'Projection d outcome attendu',
            'projected_start' => 'Depart projete',
            'final_projection' => 'Projection finale',
            'execution_roadmap' => 'Feuille de route d execution',
            'risks_and_watchouts' => 'Risques et points de vigilance',
            'recommended_countermeasures' => 'Contre-mesures recommandees',
            'recommended_action_plan' => 'Plan d action recommande',
            'steering_indicators' => 'Indicateurs de pilotage',
            'cadence' => 'Cadence',
            'duration_unit' => 'mois',
            'undefined' => 'Non defini',
            'no_project' => 'Aucun projet',
            'strategy_without_name' => 'Strategie sans nom',
            'messages' => [
                'forbidden' => 'Vous ne pouvez pas generer le playbook de cette strategie.',
                'only_approved' => 'Seules les strategies acceptees peuvent etre exportees en playbook.',
                'pdf_disabled' => 'La generation PDF est desactivee. Une version imprimable HTML a ete preparee. Ouvrez-la puis utilisez Imprimer > Enregistrer en PDF.',
                'pdf_unavailable' => 'Le service PDF est indisponible. Une version imprimable HTML a ete preparee. Ouvrez-la puis utilisez Imprimer > Enregistrer en PDF.',
            ],
        ];
    }

    /**
     * @param list<string> $messages
     *
     * @return list<string>
     */
    public function localizeMessages(array $messages, string $language): array
    {
        $language = $this->normalizeLanguage($language);

        if ($language === 'fr' || $messages === []) {
            return array_values($messages);
        }

        return $this->translator->translateBatch(array_values($messages), 'en', 'fr');
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    private function localizeContent(array $content, string $language): array
    {
        if ($language === 'fr') {
            return $content;
        }

        $localized = $content;

        foreach (['executive_summary', 'strategic_diagnosis', 'expected_outcome_summary'] as $field) {
            if (isset($localized[$field]) && is_string($localized[$field])) {
                $localized[$field] = $this->translateText($localized[$field], $language);
            }
        }

        foreach ([
            'highlights',
            'strategic_priorities',
            'opportunities',
            'risks',
            'mitigation_actions',
            'actions',
        ] as $field) {
            if (isset($localized[$field]) && is_array($localized[$field])) {
                $localized[$field] = $this->translator->translateBatch(array_values($localized[$field]), 'en', 'fr');
            }
        }

        if (isset($localized['execution_phases']) && is_array($localized['execution_phases'])) {
            foreach ($localized['execution_phases'] as $index => $phase) {
                if (!is_array($phase)) {
                    continue;
                }

                $localized['execution_phases'][$index] = [
                    'title' => $this->translateText((string) ($phase['title'] ?? ''), $language),
                    'horizon' => $this->translateText((string) ($phase['horizon'] ?? ''), $language),
                    'focus' => $this->translateText((string) ($phase['focus'] ?? ''), $language),
                ];
            }
        }

        if (isset($localized['kpis']) && is_array($localized['kpis'])) {
            foreach ($localized['kpis'] as $index => $kpi) {
                if (!is_array($kpi)) {
                    continue;
                }

                $localized['kpis'][$index] = [
                    'name' => $this->translateText((string) ($kpi['name'] ?? ''), $language),
                    'target' => $this->translateText((string) ($kpi['target'] ?? ''), $language),
                    'cadence' => $this->translateText((string) ($kpi['cadence'] ?? ''), $language),
                ];
            }
        }

        if (isset($localized['expected_outcome_chart']) && is_array($localized['expected_outcome_chart'])) {
            if (isset($localized['expected_outcome_chart']['aria_label']) && is_string($localized['expected_outcome_chart']['aria_label'])) {
                $localized['expected_outcome_chart']['aria_label'] = $this->translateText(
                    $localized['expected_outcome_chart']['aria_label'],
                    $language
                );
            }

            if (isset($localized['expected_outcome_chart']['points']) && is_array($localized['expected_outcome_chart']['points'])) {
                foreach ($localized['expected_outcome_chart']['points'] as $index => $point) {
                    if (!is_array($point)) {
                        continue;
                    }

                    $localized['expected_outcome_chart']['points'][$index]['period'] = $this->translateText(
                        (string) ($point['period'] ?? ''),
                        $language
                    );
                }
            }
        }

        return $localized;
    }

    /**
     * @param array<string, mixed> $labels
     *
     * @return array<string, string>
     */
    private function localizeStrategy(Strategie $strategy, string $language, array $labels): array
    {
        return [
            'name' => $this->firstNonEmpty(
                $this->translateNullableText($strategy->getNomStrategie(), $language),
                (string) $labels['strategy_without_name']
            ),
            'status_label' => $this->getStrategyStatusLabel($strategy->getStatusStrategie(), $language),
            'type' => $this->firstNonEmpty(
                $this->translateNullableText($strategy->getType(), $language),
                (string) $labels['undefined']
            ),
            'news' => $this->translateNullableText($strategy->getNews(), $language),
            'justification' => $this->translateNullableText($strategy->getJustification(), $language),
            'owner' => $this->buildUserFullName(
                $strategy->getUser()?->getPrenomUser(),
                $strategy->getUser()?->getNomUser(),
                (string) $labels['undefined']
            ),
        ];
    }

    /**
     * @param array<string, mixed> $labels
     *
     * @return array<string, string>|null
     */
    private function localizeProject(?Project $project, string $language, array $labels): ?array
    {
        if (!$project instanceof Project) {
            return null;
        }

        return [
            'title' => $this->firstNonEmpty(
                $this->translateNullableText($project->getTitleProj(), $language),
                (string) $labels['no_project']
            ),
            'description' => $this->translateNullableText($project->getDescriptionProj(), $language),
            'status_label' => $this->getProjectStatusLabel($project->getStateProj(), $language),
            'owner' => $this->buildUserFullName(
                $project->getUser()?->getPrenomUser(),
                $project->getUser()?->getNomUser(),
                (string) $labels['undefined']
            ),
        ];
    }

    /**
     * @param array<string, mixed> $labels
     *
     * @return list<array{name: string, description: string, priority_label: string, priority_badge: string}>
     */
    private function localizeObjectives(Strategie $strategy, string $language, array $labels): array
    {
        $objectives = [];

        foreach ($strategy->getObjectives() as $objective) {
            $priorityLabel = $this->getObjectivePriorityLabel($objective, $language);

            $objectives[] = [
                'name' => $this->firstNonEmpty(
                    $this->translateNullableText($objective->getNomObj(), $language),
                    (string) $labels['objective_fallback']
                ),
                'description' => $this->firstNonEmpty(
                    $this->translateNullableText($objective->getDescriptionOb(), $language),
                    (string) $labels['no_description']
                ),
                'priority_label' => $priorityLabel,
                'priority_badge' => sprintf((string) $labels['objective_priority_pattern'], mb_strtolower($priorityLabel)),
            ];
        }

        return $objectives;
    }

    private function translateText(string $text, string $language): string
    {
        $translated = $this->translateNullableText($text, $language);

        return $translated !== null ? $translated : '';
    }

    private function translateNullableText(?string $text, string $language): ?string
    {
        if ($text === null) {
            return null;
        }

        $trimmed = trim($text);
        if ($trimmed === '' || $language === 'fr') {
            return $trimmed;
        }

        // LibreTranslate disabled for teammate environments without the service.
        // Uncomment when translation service is available again:
        // return $this->translator->translate($trimmed, 'en', 'fr');
        return $trimmed;
    }

    private function firstNonEmpty(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    private function buildUserFullName(?string $firstName, ?string $lastName, string $fallback): string
    {
        $label = trim(sprintf('%s %s', (string) $firstName, (string) $lastName));

        return $label !== '' ? $label : $fallback;
    }

    private function getStrategyStatusLabel(?string $status, string $language): string
    {
        $labels = $language === 'en'
            ? [
                Strategie::STATUS_PENDING => 'Pending',
                Strategie::STATUS_APPROVED => 'Approved',
                Strategie::STATUS_REJECTED => 'Rejected',
                Strategie::STATUS_IN_PROGRESS => 'In progress',
                Strategie::STATUS_UNASSIGNED => 'Unassigned',
            ]
            : [
                Strategie::STATUS_PENDING => 'En attente',
                Strategie::STATUS_APPROVED => 'Acceptee',
                Strategie::STATUS_REJECTED => 'Refusee',
                Strategie::STATUS_IN_PROGRESS => 'En cours',
                Strategie::STATUS_UNASSIGNED => 'Non affectee',
            ];

        return $labels[$status] ?? ($language === 'en' ? 'Not defined' : 'Non defini');
    }

    private function getProjectStatusLabel(?string $status, string $language): string
    {
        $labels = $language === 'en'
            ? [
                Project::STATUS_PENDING => 'Pending',
                Project::STATUS_ACCEPTED => 'Accepted',
                Project::STATUS_REFUSED => 'Rejected',
                'ARCHIVED' => 'Archived',
            ]
            : [
                Project::STATUS_PENDING => 'En attente',
                Project::STATUS_ACCEPTED => 'Accepte',
                Project::STATUS_REFUSED => 'Refuse',
                'ARCHIVED' => 'Archive',
            ];

        return $labels[$status] ?? ($language === 'en' ? 'Not defined' : 'Non defini');
    }

    private function getObjectivePriorityLabel(Objective $objective, string $language): string
    {
        $labels = $language === 'en'
            ? [
                Objective::PRIORITY_LOW => 'Low',
                Objective::PRIORITY_MEDIUM => 'Medium',
                Objective::PRIORITY_HIGH => 'High',
                Objective::PRIORITY_URGENT => 'Urgent',
            ]
            : [
                Objective::PRIORITY_LOW => 'Basse',
                Objective::PRIORITY_MEDIUM => 'Moyenne',
                Objective::PRIORITY_HIGH => 'Haute',
                Objective::PRIORITY_URGENT => 'Urgente',
            ];

        return $labels[$objective->getPriorityOb()] ?? ($language === 'en' ? 'Undefined' : 'Non definie');
    }
}
