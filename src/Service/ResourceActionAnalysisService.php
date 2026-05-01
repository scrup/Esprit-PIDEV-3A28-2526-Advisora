<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Moteur d'analyse metier pour la gestion des ressources (back office).
 *
 * Ce service reste deterministe:
 * - aucun LLM n'est necessaire pour les calculs,
 * - les signaux sont calcules via des regles explicites,
 * - le resultat est stable, testable et peu couteux.
 */
class ResourceActionAnalysisService
{
    private const PRIORITY_RANK = [
        'haute' => 3,
        'moyenne' => 2,
        'basse' => 1,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Pipeline principal:
     * 1) collecte des donnees (stock total/reserve, statut, prix, fournisseur),
     * 2) detection des signaux de risque,
     * 3) transformation en actions priorisees,
     * 4) synthese globale + KPI.
     *
     * @return array<string, mixed>
     */
    public function analyze(): array
    {
        $resources = $this->fetchResourceRows();
        $priceStats = $this->computePriceStats($resources);

        [$actions, $resourceSignals, $supplierRisks] = $this->buildActionPlan($resources, $priceStats);

        usort($actions, static function (array $left, array $right): int {
            $priorityDiff = ((int) ($right['priority_rank'] ?? 0)) <=> ((int) ($left['priority_rank'] ?? 0));
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }

            $confidenceDiff = ((int) ($right['confidence_pct'] ?? 0)) <=> ((int) ($left['confidence_pct'] ?? 0));
            if ($confidenceDiff !== 0) {
                return $confidenceDiff;
            }

            return ((int) ($left['resource_id'] ?? PHP_INT_MAX)) <=> ((int) ($right['resource_id'] ?? PHP_INT_MAX));
        });

        $criticalCount = count(array_filter(
            $resourceSignals,
            static fn (array $row): bool => in_array('stock_critique', $row['signals'] ?? [], true)
        ));
        $overstockCount = count(array_filter(
            $resourceSignals,
            static fn (array $row): bool => in_array('surstock', $row['signals'] ?? [], true)
        ));
        $priceAnomalyCount = count(array_filter(
            $resourceSignals,
            static fn (array $row): bool => in_array('prix_anormal', $row['signals'] ?? [], true)
        ));
        $highPriorityActions = count(array_filter(
            $actions,
            static fn (array $action): bool => ($action['priority'] ?? '') === 'haute'
        ));

        $availableFilters = [
            'priorities' => ['haute', 'moyenne', 'basse'],
            'action_codes' => array_values(array_unique(array_map(
                static fn (array $action): string => (string) ($action['action_code'] ?? ''),
                $actions
            ))),
        ];
        sort($availableFilters['action_codes']);

        $totalResources = count($resources);
        $totalSuppliers = count(array_unique(array_map(
            static fn (array $row): string => (string) ($row['supplier_name'] ?? 'Non renseigne'),
            $resources
        )));

        return [
            'generated_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'engine' => 'resource_actions_v1',
            'dataset' => [
                'total_resources' => $totalResources,
                'total_suppliers' => $totalSuppliers,
            ],
            'kpis' => [
                'stock_critique' => $criticalCount,
                'surstock' => $overstockCount,
                'prix_anormal' => $priceAnomalyCount,
                'fournisseurs_a_risque' => count($supplierRisks),
                'actions_total' => count($actions),
                'actions_priorite_haute' => $highPriorityActions,
            ],
            'summary' => $this->buildSummary(
                $totalResources,
                $criticalCount,
                $overstockCount,
                $priceAnomalyCount,
                count($supplierRisks),
                count($actions),
                $highPriorityActions
            ),
            'price_stats' => $priceStats,
            'actions' => $actions,
            'resource_signals' => $resourceSignals,
            'supplier_risks' => array_values($supplierRisks),
            'available_filters' => $availableFilters,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchResourceRows(): array
    {
        $connection = $this->entityManager->getConnection();
        $quantityColumn = $this->detectQuantityColumn($connection);
        $reservedExpression = $quantityColumn !== null
            ? 'COALESCE(SUM(pr.' . $quantityColumn . '), 0)'
            : 'COUNT(pr.idRs)';

        $rows = $connection->fetchAllAssociative(
            'SELECT
                r.idRs AS resource_id,
                COALESCE(NULLIF(TRIM(r.nomRs), \'\'), CONCAT(\'Ressource #\', r.idRs)) AS resource_name,
                UPPER(TRIM(COALESCE(r.availabilityStatusRs, \'\'))) AS status,
                COALESCE(r.QuantiteRs, 0) AS stock_total,
                COALESCE(r.prixRs, 0) AS price,
                COALESCE(NULLIF(TRIM(cf.fournisseur), \'\'), NULLIF(TRIM(cf.nomFr), \'\'), \'Non renseigne\') AS supplier_name,
                ' . $reservedExpression . ' AS stock_reserved
            FROM resources r
            LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
            LEFT JOIN project_resources pr ON pr.idRs = r.idRs
            GROUP BY
                r.idRs,
                r.nomRs,
                r.availabilityStatusRs,
                r.QuantiteRs,
                r.prixRs,
                cf.fournisseur,
                cf.nomFr
            ORDER BY r.idRs DESC'
        );

        $normalized = [];
        foreach ($rows as $row) {
            $stockTotal = max(0, (int) ($row['stock_total'] ?? 0));
            $stockReserved = max(0, (int) ($row['stock_reserved'] ?? 0));
            $status = strtoupper(trim((string) ($row['status'] ?? '')));
            if ($status === '') {
                $status = 'AVAILABLE';
            }

            $normalized[] = [
                'resource_id' => (int) ($row['resource_id'] ?? 0),
                'resource_name' => trim((string) ($row['resource_name'] ?? 'Ressource')),
                'status' => $status,
                'stock_total' => $stockTotal,
                'stock_reserved' => $stockReserved,
                'stock_available' => max(0, $stockTotal - $stockReserved),
                'price' => max(0.0, (float) ($row['price'] ?? 0.0)),
                'supplier_name' => trim((string) ($row['supplier_name'] ?? 'Non renseigne')) ?: 'Non renseigne',
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $resources
     * @return array<string, float>
     */
    private function computePriceStats(array $resources): array
    {
        $prices = array_values(array_filter(array_map(
            static fn (array $row): float => max(0.0, (float) ($row['price'] ?? 0.0)),
            $resources
        ), static fn (float $price): bool => $price > 0.0));

        if ($prices === []) {
            return ['median' => 0.0, 'average' => 0.0, 'min' => 0.0, 'max' => 0.0];
        }

        sort($prices);
        $count = count($prices);
        $mid = (int) floor($count / 2);
        $median = $count % 2 === 0
            ? ($prices[$mid - 1] + $prices[$mid]) / 2
            : $prices[$mid];

        return [
            'median' => (float) $median,
            'average' => (float) (array_sum($prices) / $count),
            'min' => (float) $prices[0],
            'max' => (float) $prices[$count - 1],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $resources
     * @param array<string, float> $priceStats
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<string, array<string, mixed>>}
     */
    private function buildActionPlan(array $resources, array $priceStats): array
    {
        $actions = [];
        $resourceSignals = [];
        $supplierHealth = [];
        $medianPrice = (float) ($priceStats['median'] ?? 0.0);

        foreach ($resources as $resource) {
            $resourceId = (int) ($resource['resource_id'] ?? 0);
            $resourceName = (string) ($resource['resource_name'] ?? 'Ressource');
            $supplierName = (string) ($resource['supplier_name'] ?? 'Non renseigne');
            $status = (string) ($resource['status'] ?? 'AVAILABLE');
            $stockTotal = max(0, (int) ($resource['stock_total'] ?? 0));
            $stockReserved = max(0, (int) ($resource['stock_reserved'] ?? 0));
            $stockAvailable = max(0, (int) ($resource['stock_available'] ?? 0));
            $price = max(0.0, (float) ($resource['price'] ?? 0.0));

            $signals = [];

            // Signal 1: stock critique.
            $criticalThreshold = max(2, (int) ceil($stockTotal * 0.15));
            $isCritical = $stockTotal <= 0 || $stockAvailable <= $criticalThreshold || $status === 'UNAVAILABLE';
            if ($isCritical) {
                $signals[] = 'stock_critique';

                $priority = $stockAvailable <= 0 || $status === 'UNAVAILABLE' ? 'haute' : 'moyenne';
                $confidence = $stockAvailable <= 0 ? 96 : min(95, 76 + max(0, $criticalThreshold - $stockAvailable) * 4);
                $delay = $stockAvailable <= 0 ? 'immediat' : '24h-48h';

                $this->appendAction(
                    $actions,
                    $priority,
                    'STOCK_CRITIQUE_REAPPRO',
                    'Risque de rupture et blocage des engagements projets.',
                    sprintf(
                        'Stock total=%d, reserve=%d, disponible=%d, seuil=%d, statut=%s.',
                        $stockTotal,
                        $stockReserved,
                        $stockAvailable,
                        $criticalThreshold,
                        $status
                    ),
                    $confidence,
                    $delay,
                    $resourceId,
                    $resourceName,
                    $supplierName,
                    'stock_critique'
                );
            }

            // Signal 2: surstock.
            $surstockThreshold = max(8, (int) ceil($stockTotal * 0.70));
            $isOverstock = $stockTotal >= 10
                && $stockAvailable >= $surstockThreshold
                && $stockReserved <= (int) ceil($stockTotal * 0.15);
            if ($isOverstock) {
                $signals[] = 'surstock';

                $confidence = min(90, 68 + (int) round(($stockAvailable / max(1, $stockTotal)) * 20));
                $this->appendAction(
                    $actions,
                    'moyenne',
                    'SURSTOCK_REEQUILIBRER',
                    'Capital immobilise et rotation stock insuffisante.',
                    sprintf(
                        'Disponible=%d sur %d avec reserve faible (%d).',
                        $stockAvailable,
                        $stockTotal,
                        $stockReserved
                    ),
                    $confidence,
                    '7 jours',
                    $resourceId,
                    $resourceName,
                    $supplierName,
                    'surstock'
                );
            }

            // Signal 3: prix anormal.
            $isPriceAnomaly = false;
            if ($medianPrice > 0.0 && $price > 0.0) {
                $highThreshold = $medianPrice * 1.8;
                $lowThreshold = $medianPrice * 0.45;
                $isPriceAnomaly = $price > $highThreshold || $price < $lowThreshold;

                if ($isPriceAnomaly) {
                    $signals[] = 'prix_anormal';
                    $distance = abs($price - $medianPrice) / $medianPrice;
                    $confidence = min(93, 70 + (int) round($distance * 24));

                    $this->appendAction(
                        $actions,
                        $distance > 1.6 ? 'haute' : 'moyenne',
                        'PRIX_ANORMAL_AUDITER',
                        'Risque de marge non maitrisee ou de perte de competitivite.',
                        sprintf(
                            'Prix=%.3f, mediane=%.3f, ecart=%.1f%%.',
                            $price,
                            $medianPrice,
                            $distance * 100
                        ),
                        $confidence,
                        '48h',
                        $resourceId,
                        $resourceName,
                        $supplierName,
                        'prix_anormal'
                    );
                }
            }

            $resourceSignals[] = [
                'resource_id' => $resourceId,
                'resource_name' => $resourceName,
                'supplier_name' => $supplierName,
                'status' => $status,
                'stock_total' => $stockTotal,
                'stock_reserved' => $stockReserved,
                'stock_available' => $stockAvailable,
                'price' => $price,
                'signals' => $signals,
                'risk_level' => in_array('stock_critique', $signals, true)
                    ? 'haute'
                    : (in_array('prix_anormal', $signals, true) || in_array('surstock', $signals, true) ? 'moyenne' : 'basse'),
            ];

            if (!isset($supplierHealth[$supplierName])) {
                $supplierHealth[$supplierName] = [
                    'supplier_name' => $supplierName,
                    'resources_count' => 0,
                    'critical_count' => 0,
                    'unavailable_count' => 0,
                    'overstock_count' => 0,
                ];
            }

            $supplierHealth[$supplierName]['resources_count']++;
            if ($isCritical) {
                $supplierHealth[$supplierName]['critical_count']++;
            }
            if ($status === 'UNAVAILABLE') {
                $supplierHealth[$supplierName]['unavailable_count']++;
            }
            if ($isOverstock) {
                $supplierHealth[$supplierName]['overstock_count']++;
            }
        }

        $supplierRisks = [];
        foreach ($supplierHealth as $supplierName => $supplierStat) {
            $resourceCount = max(1, (int) $supplierStat['resources_count']);
            $criticalCount = (int) $supplierStat['critical_count'];
            $unavailableCount = (int) $supplierStat['unavailable_count'];
            $overstockCount = (int) $supplierStat['overstock_count'];

            $riskScore = (($criticalCount * 2.0) + ($unavailableCount * 2.2) + ($overstockCount * 1.0)) / $resourceCount;
            $isSupplierRisk = $resourceCount >= 2 && ($riskScore >= 1.35 || $criticalCount >= 2 || $unavailableCount >= 1);

            if (!$isSupplierRisk) {
                continue;
            }

            $supplierRisks[$supplierName] = [
                'supplier_name' => $supplierName,
                'resources_count' => $resourceCount,
                'critical_count' => $criticalCount,
                'unavailable_count' => $unavailableCount,
                'overstock_count' => $overstockCount,
                'risk_score' => round($riskScore, 2),
            ];

            $priority = $criticalCount >= 2 || $unavailableCount >= 1 ? 'haute' : 'moyenne';
            $confidence = min(95, 74 + (int) round($riskScore * 10));

            $this->appendAction(
                $actions,
                $priority,
                'FOURNISSEUR_RISQUE_DIVERSIFIER',
                'Dependance fournisseur pouvant affecter plusieurs ressources.',
                sprintf(
                    'Fournisseur=%s, ressources=%d, critiques=%d, indisponibles=%d, surstock=%d.',
                    $supplierName,
                    $resourceCount,
                    $criticalCount,
                    $unavailableCount,
                    $overstockCount
                ),
                $confidence,
                '3 jours',
                null,
                'Portefeuille fournisseur',
                $supplierName,
                'fournisseur_risque'
            );
        }

        return [$actions, $resourceSignals, $supplierRisks];
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     */
    private function appendAction(
        array &$actions,
        string $priority,
        string $actionCode,
        string $impactMetier,
        string $justification,
        int $confidencePct,
        string $delay,
        ?int $resourceId,
        string $resourceName,
        string $supplierName,
        string $signalType
    ): void {
        $normalizedPriority = strtolower(trim($priority));
        if (!isset(self::PRIORITY_RANK[$normalizedPriority])) {
            $normalizedPriority = 'basse';
        }

        $actions[] = [
            'priority' => $normalizedPriority,
            'priority_rank' => self::PRIORITY_RANK[$normalizedPriority],
            'action_code' => strtoupper(trim($actionCode)),
            'impact_metier' => trim($impactMetier),
            'justification' => trim($justification),
            'confidence_pct' => max(50, min(99, $confidencePct)),
            'delay' => trim($delay),
            'resource_id' => $resourceId,
            'resource_name' => trim($resourceName),
            'supplier_name' => trim($supplierName),
            'signal_type' => trim($signalType),
        ];
    }

    /**
     * @return array{headline:string, narrative:string}
     */
    private function buildSummary(
        int $resourceCount,
        int $criticalCount,
        int $overstockCount,
        int $priceAnomalyCount,
        int $supplierRiskCount,
        int $actionCount,
        int $highPriorityCount
    ): array {
        return [
            'headline' => sprintf(
                '%d action(s) recommandee(s), dont %d en priorite haute.',
                $actionCount,
                $highPriorityCount
            ),
            'narrative' => sprintf(
                'Analyse sur %d ressources: %d stock critique, %d surstock, %d prix anormal, %d fournisseur a risque.',
                $resourceCount,
                $criticalCount,
                $overstockCount,
                $priceAnomalyCount,
                $supplierRiskCount
            ),
        ];
    }

    private function detectQuantityColumn(Connection $connection): ?string
    {
        $columns = $connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_resources'"
        );

        foreach ($columns as $column) {
            $normalized = strtolower((string) $column);
            if (in_array($normalized, ['qtyallocated', 'quantity', 'qty'], true)) {
                return (string) $column;
            }
        }

        return null;
    }
}
