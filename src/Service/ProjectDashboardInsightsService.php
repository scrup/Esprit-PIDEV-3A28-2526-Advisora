<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;

final class ProjectDashboardInsightsService
{
    public function __construct(private ProjectRepository $projectRepository)
    {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function buildClientDashboard(User $user, array $filters = []): array
    {
        $projects = array_values($this->projectRepository->findFrontProjects($filters, $user, false));

        return $this->buildDashboardPayload(
            $projects,
            'client',
            'Client',
            $this->formatActiveFilters($filters, 'front')
        );
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function buildBackOfficeDashboard(array $filters = []): array
    {
        $view = (string) ($filters['_view'] ?? 'back');
        unset($filters['_view']);

        $projects = array_values(
            $view === 'front_global'
                ? $this->projectRepository->findFrontProjects($filters, null, true)
                : $this->projectRepository->findBackOfficeProjects($filters)
        );

        return $this->buildDashboardPayload(
            $projects,
            'back_office',
            'Gerant / Admin',
            $this->formatActiveFilters($filters, $view === 'front_global' ? 'front' : 'back')
        );
    }

    /**
     * @param list<Project> $projects
     * @param list<array{label: string, value: string}> $filters
     *
     * @return array<string, mixed>
     */
    private function buildDashboardPayload(array $projects, string $scope, string $roleLabel, array $filters): array
    {
        $statusCounters = $this->projectRepository->getScopedStatusCounters($projects);
        $typeCounters = $this->projectRepository->getScopedTypeCounters($projects);
        $monthlyCounters = $this->projectRepository->getScopedMonthlyCreationStats($projects);
        $averageBudgets = $this->projectRepository->getScopedAverageBudgetsByStatus($projects);

        $totalProjects = count($projects);
        $totalBudget = 0.0;
        $budgetCount = 0;

        foreach ($projects as $project) {
            $budget = (float) ($project->getLegacyBudget() ?? 0.0);

            if ($budget <= 0) {
                continue;
            }

            $totalBudget += $budget;
            ++$budgetCount;
        }

        $acceptedProjects = $statusCounters[Project::STATUS_ACCEPTED];
        $decisionTotal = $acceptedProjects + $statusCounters[Project::STATUS_REFUSED];

        return [
            'scope' => $scope,
            'summary' => [
                'total_projects' => $totalProjects,
                'pending_projects' => $statusCounters[Project::STATUS_PENDING],
                'accepted_projects' => $acceptedProjects,
                'refused_projects' => $statusCounters[Project::STATUS_REFUSED],
                'total_budget' => round($totalBudget, 2),
                'average_budget' => $budgetCount > 0 ? round($totalBudget / $budgetCount, 2) : 0.0,
                'active_types' => count($typeCounters),
                'acceptance_rate' => $decisionTotal > 0 ? round(($acceptedProjects / $decisionTotal) * 100, 1) : 0.0,
            ],
            'charts' => [
                'status' => [
                    'labels' => ['En attente', 'Acceptes', 'Refuses'],
                    'values' => [
                        $statusCounters[Project::STATUS_PENDING],
                        $statusCounters[Project::STATUS_ACCEPTED],
                        $statusCounters[Project::STATUS_REFUSED],
                    ],
                ],
                'types' => [
                    'labels' => array_keys($typeCounters),
                    'values' => array_values($typeCounters),
                ],
                'timeline' => [
                    'labels' => array_map([$this, 'formatMonthLabel'], array_keys($monthlyCounters)),
                    'values' => array_values($monthlyCounters),
                ],
                'budgets' => [
                    'labels' => ['En attente', 'Acceptes', 'Refuses'],
                    'values' => [
                        $averageBudgets[Project::STATUS_PENDING],
                        $averageBudgets[Project::STATUS_ACCEPTED],
                        $averageBudgets[Project::STATUS_REFUSED],
                    ],
                ],
            ],
            'projects' => $projects,
            'export_meta' => [
                'generated_at' => new \DateTimeImmutable(),
                'role_label' => $roleLabel,
                'filters' => $filters,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{label: string, value: string}>
     */
    private function formatActiveFilters(array $filters, string $context): array
    {
        $activeFilters = [];

        $map = $context === 'back'
            ? [
                'q' => 'Recherche',
                'status' => 'Statut',
                'owner' => 'Porteur',
            ]
            : [
                'q' => 'Recherche',
                'status' => 'Statut',
                'type' => 'Type',
                'min_price' => 'Budget min',
                'max_price' => 'Budget max',
            ];

        foreach ($map as $key => $label) {
            $value = trim((string) ($filters[$key] ?? ''));

            if ($value === '') {
                continue;
            }

            $activeFilters[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $activeFilters;
    }

    private function formatMonthLabel(string $key): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m', $key);

        if (!$date instanceof \DateTimeImmutable) {
            return $key;
        }

        $months = [
            '01' => 'Jan',
            '02' => 'Fev',
            '03' => 'Mar',
            '04' => 'Avr',
            '05' => 'Mai',
            '06' => 'Juin',
            '07' => 'Juil',
            '08' => 'Aout',
            '09' => 'Sept',
            '10' => 'Oct',
            '11' => 'Nov',
            '12' => 'Dec',
        ];

        $month = $months[$date->format('m')];

        return sprintf('%s %s', $month, $date->format('Y'));
    }
}