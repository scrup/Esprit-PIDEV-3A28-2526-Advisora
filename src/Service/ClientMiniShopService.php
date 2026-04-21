<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class ClientMiniShopService
{
    private const LISTING_LISTED = 'LISTED';

    private ?string $quantityColumnCache = null;
    private bool $quantityColumnDetected = false;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Construit les donnees de la page boutique: catalogue, panier et reservations.
     *
     * @return array<string, mixed>
     */
    public function buildPageData(User $client, ?int $supplierId, string $search = '', array $cart = []): array
    {
        $this->assertClient($client);
        $connection = $this->entityManager->getConnection();

        $suppliers = $connection->fetchAllAssociative(
            'SELECT idFr, nomFr, fournisseur FROM cataloguefournisseur ORDER BY nomFr ASC, idFr ASC'
        );

        $projects = $connection->fetchAllAssociative(
            'SELECT idProj, titleProj FROM projects WHERE idClient = ? ORDER BY idProj DESC',
            [(int) $client->getIdUser()]
        );

        $resources = $this->fetchCatalogResources($connection, $supplierId, $search);
        $reservations = $this->fetchClientReservations($connection, $client);
        $cartItems = $this->buildCartItemsFromSession($connection, $cart);

        return [
            'selected_supplier_id' => $supplierId,
            'selected_search' => $search,
            'suppliers' => $suppliers,
            'projects' => $projects,
            'resources' => $resources,
            'cart_items' => $cartItems,
            'reservations' => $reservations,
            'catalog_stats' => [
                'resources_count' => count($resources),
                'suppliers_count' => count($suppliers),
                'reserved_units' => array_sum(array_map(static fn (array $row): int => (int) $row['reserved_stock'], $resources)),
                'available_units' => array_sum(array_map(static fn (array $row): int => (int) $row['available_stock'], $resources)),
            ],
            'cart_stats' => [
                'items_count' => count($cartItems),
                'units_count' => array_sum(array_map(static fn (array $row): int => (int) $row['quantity'], $cartItems)),
                'subtotal' => array_sum(array_map(static fn (array $row): float => (float) $row['line_total'], $cartItems)),
            ],
            'reservation_stats' => [
                'reservations_count' => count($reservations),
                'reserved_units' => array_sum(array_map(static fn (array $row): int => (int) $row['quantity'], $reservations)),
                'projects_count' => count(array_unique(array_map(static fn (array $row): int => (int) $row['project_id'], $reservations))),
            ],
        ];
    }

    /**
     * @param array<int, int> $cart
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCartItems(User $client, array $cart): array
    {
        $this->assertClient($client);

        if ($cart === []) {
            return [];
        }

        return $this->buildCartItemsFromSession($this->entityManager->getConnection(), $cart);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResourceSnapshot(User $client, int $resourceId): ?array
    {
        $this->assertClient($client);

        if ($resourceId <= 0) {
            return null;
        }

        $rows = $this->fetchResourcesByIds($this->entityManager->getConnection(), [$resourceId]);

        return $rows[0] ?? null;
    }

    public function reserve(User $client, int $resourceId, int $quantity, ?int $projectId): void
    {
        $this->assertClient($client);

        if ($resourceId <= 0 || $quantity <= 0) {
            throw new \InvalidArgumentException('La reservation demandee est invalide.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $resourceRow = $connection->fetchAssociative(
                'SELECT idRs, QuantiteRs FROM resources WHERE idRs = ?',
                [$resourceId]
            );

            if ($resourceRow === false) {
                throw new \InvalidArgumentException('Ressource introuvable.');
            }

            $availableStock = max(0, (int) $resourceRow['QuantiteRs'] - $this->getReservedStock($connection, $resourceId));
            if ($quantity > $availableStock) {
                throw new \InvalidArgumentException(sprintf('Stock insuffisant. Disponible: %d', $availableStock));
            }

            $resolvedProjectId = $this->resolveProjectId($connection, $client, $projectId);
            $this->lockOwnedProjectsForUpdate($connection, (int) $client->getIdUser(), [$resolvedProjectId]);
            $quantityColumn = $this->detectQuantityColumn($connection);

            // Si une colonne quantite existe, on suit cette structure.
            if ($quantityColumn !== null) {
                $existing = (int) $connection->fetchOne(
                    'SELECT COUNT(*) FROM project_resources WHERE idProj = ? AND idRs = ?',
                    [$resolvedProjectId, $resourceId]
                );

                if ($existing > 0) {
                    $connection->executeStatement(
                        'UPDATE project_resources SET ' . $quantityColumn . ' = ' . $quantityColumn . ' + ? WHERE idProj = ? AND idRs = ?',
                        [$quantity, $resolvedProjectId, $resourceId]
                    );
                } else {
                    $connection->insert('project_resources', [
                        'idProj' => $resolvedProjectId,
                        'idRs' => $resourceId,
                        $quantityColumn => $quantity,
                    ]);
                }
            } else {
                // Sinon on respecte la logique Java legacy: 1 ligne = 1 unite reservee.
                $this->insertUnitRows($connection, $resolvedProjectId, $resourceId, $quantity);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    public function updateReservation(User $client, int $projectId, int $resourceId, int $newQuantity, ?int $targetProjectId): void
    {
        $this->assertClient($client);

        if ($projectId <= 0 || $resourceId <= 0 || $newQuantity <= 0) {
            throw new \InvalidArgumentException('La reservation a modifier est invalide.');
        }

        $connection = $this->entityManager->getConnection();
        $clientId = (int) $client->getIdUser();
        $quantityColumn = $this->detectQuantityColumn($connection);
        $connection->beginTransaction();

        try {
            $resolvedTargetProjectId = $targetProjectId !== null && $targetProjectId > 0
                ? $this->resolveOwnedProject($connection, $client, $targetProjectId)
                : $projectId;

            $this->lockOwnedProjectsForUpdate($connection, $clientId, [$projectId, $resolvedTargetProjectId]);

            $currentQuantity = $this->getOwnedReservationQuantity($connection, $client, $projectId, $resourceId);
            if ($currentQuantity <= 0) {
                throw new \InvalidArgumentException('Reservation introuvable.');
            }

            $listedQty = $this->listedRemainingForProjectResource($connection, $clientId, $projectId, $resourceId);
            if ($resolvedTargetProjectId === $projectId && $newQuantity < $listedQty) {
                throw new \InvalidArgumentException(
                    sprintf('Impossible de descendre sous les annonces actives. Minimum: %d', $listedQty)
                );
            }
            if ($resolvedTargetProjectId !== $projectId && $listedQty > 0) {
                throw new \InvalidArgumentException(
                    'Impossible de deplacer cette reservation: des annonces actives existent sur le projet source.'
                );
            }

            $resourceStock = (int) $connection->fetchOne('SELECT QuantiteRs FROM resources WHERE idRs = ?', [$resourceId]);
            $reservedTotal = $this->getReservedStock($connection, $resourceId);
            $availableExcludingCurrent = max(0, $resourceStock - ($reservedTotal - $currentQuantity));

            if ($newQuantity > max($currentQuantity, $availableExcludingCurrent)) {
                throw new \InvalidArgumentException('Stock insuffisant pour la mise a jour.');
            }

            if ($resolvedTargetProjectId === $projectId) {
                if ($quantityColumn !== null) {
                    $connection->executeStatement(
                        'UPDATE project_resources SET ' . $quantityColumn . ' = ? WHERE idProj = ? AND idRs = ?',
                        [$newQuantity, $projectId, $resourceId]
                    );
                } else {
                    $delta = $newQuantity - $currentQuantity;

                    if ($delta > 0) {
                        $this->insertUnitRows($connection, $projectId, $resourceId, $delta);
                    } elseif ($delta < 0) {
                        $this->deleteUnitRows($connection, $projectId, $resourceId, abs($delta));
                    }
                }
            } else {
                $this->moveReservationToTargetProject(
                    $connection,
                    $projectId,
                    $resolvedTargetProjectId,
                    $resourceId,
                    $newQuantity,
                    $quantityColumn
                );
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    public function deleteReservation(User $client, int $projectId, int $resourceId): void
    {
        $this->assertClient($client);
        $connection = $this->entityManager->getConnection();
        $clientId = (int) $client->getIdUser();
        $connection->beginTransaction();

        try {
            $this->lockOwnedProjectsForUpdate($connection, $clientId, [$projectId]);

            $ownedQuantity = $this->getOwnedReservationQuantity($connection, $client, $projectId, $resourceId);
            if ($ownedQuantity <= 0) {
                throw new \InvalidArgumentException('Reservation introuvable.');
            }

            $listedQty = $this->listedRemainingForProjectResource($connection, $clientId, $projectId, $resourceId);
            if ($listedQty > 0) {
                throw new \InvalidArgumentException(
                    'Impossible de supprimer cette reservation: des annonces actives existent encore.'
                );
            }

            $connection->executeStatement(
                'DELETE pr FROM project_resources pr INNER JOIN projects p ON p.idProj = pr.idProj WHERE pr.idProj = ? AND pr.idRs = ? AND p.idClient = ?',
                [$projectId, $resourceId, $clientId]
            );

            $connection->commit();
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCatalogResources(Connection $connection, ?int $supplierId, string $search): array
    {
        $quantityAggregation = $this->quantityAggregateSql($connection, 'pr');

        $sql = '
            SELECT
                r.idRs,
                r.nomRs,
                r.prixRs,
                r.QuantiteRs,
                r.availabilityStatusRs,
                r.idFr,
                COALESCE(NULLIF(cf.fournisseur, \'\'), cf.nomFr) AS fournisseur_name,
                COALESCE(stock.reserved_stock, 0) AS reserved_stock,
                GREATEST(r.QuantiteRs - COALESCE(stock.reserved_stock, 0), 0) AS available_stock
            FROM resources r
            LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
            LEFT JOIN (
                SELECT pr.idRs, ' . $quantityAggregation . ' AS reserved_stock
                FROM project_resources pr
                GROUP BY pr.idRs
            ) stock ON stock.idRs = r.idRs';

        $parameters = [];
        $conditions = [];
        if ($supplierId !== null && $supplierId > 0) {
            $conditions[] = 'r.idFr = :supplierId';
            $parameters['supplierId'] = $supplierId;
        }

        $normalizedSearch = trim($search);
        if ($normalizedSearch !== '') {
            $conditions[] = "LOWER(CONCAT_WS(' ', COALESCE(r.nomRs, ''), COALESCE(cf.fournisseur, ''), COALESCE(cf.nomFr, ''), COALESCE(r.availabilityStatusRs, ''))) LIKE :search";
            $parameters['search'] = '%' . strtolower($normalizedSearch) . '%';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY r.idRs DESC';

        return $connection->fetchAllAssociative($sql, $parameters);
    }

    /**
     * @param array<int> $resourceIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchResourcesByIds(Connection $connection, array $resourceIds): array
    {
        $resourceIds = array_values(array_unique(array_filter(array_map(static fn (mixed $value): int => (int) $value, $resourceIds), static fn (int $value): bool => $value > 0)));

        if ($resourceIds === []) {
            return [];
        }

        $quantityAggregation = $this->quantityAggregateSql($connection, 'pr');
        $placeholders = implode(', ', array_fill(0, count($resourceIds), '?'));

        return $connection->fetchAllAssociative(
            '
            SELECT
                r.idRs,
                r.nomRs,
                r.prixRs,
                r.QuantiteRs,
                r.availabilityStatusRs,
                r.idFr,
                COALESCE(NULLIF(cf.fournisseur, \'\'), cf.nomFr) AS fournisseur_name,
                COALESCE(stock.reserved_stock, 0) AS reserved_stock,
                GREATEST(r.QuantiteRs - COALESCE(stock.reserved_stock, 0), 0) AS available_stock
            FROM resources r
            LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
            LEFT JOIN (
                SELECT pr.idRs, ' . $quantityAggregation . ' AS reserved_stock
                FROM project_resources pr
                GROUP BY pr.idRs
            ) stock ON stock.idRs = r.idRs
            WHERE r.idRs IN (' . $placeholders . ')
            ORDER BY r.idRs DESC
            ',
            $resourceIds
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchClientReservations(Connection $connection, User $client): array
    {
        $quantityAggregation = $this->quantityAggregateSql($connection, 'pr');

        return $connection->fetchAllAssociative(
            '
            SELECT
                pr.idProj AS project_id,
                pr.idRs AS resource_id,
                p.titleProj AS project_title,
                r.nomRs AS resource_name,
                COALESCE(NULLIF(cf.fournisseur, \'\'), cf.nomFr) AS fournisseur_name,
                ' . $quantityAggregation . ' AS quantity
            FROM project_resources pr
            INNER JOIN projects p ON p.idProj = pr.idProj
            INNER JOIN resources r ON r.idRs = pr.idRs
            LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
            WHERE p.idClient = ?
            GROUP BY pr.idProj, pr.idRs, p.titleProj, r.nomRs, cf.fournisseur, cf.nomFr
            ORDER BY pr.idProj DESC, pr.idRs DESC
            ',
            [(int) $client->getIdUser()]
        );
    }

    /**
     * @param array<int, int> $cart
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCartItemsFromSession(Connection $connection, array $cart): array
    {
        if ($cart === []) {
            return [];
        }

        $resourcesById = [];
        foreach ($this->fetchResourcesByIds($connection, array_keys($cart)) as $resource) {
            $resourcesById[(int) $resource['idRs']] = $resource;
        }

        $items = [];
        foreach ($cart as $resourceId => $quantity) {
            $resolvedResourceId = (int) $resourceId;
            $resolvedQuantity = max(1, (int) $quantity);
            $resource = $resourcesById[$resolvedResourceId] ?? null;

            if ($resource === null) {
                continue;
            }

            $unitPrice = (float) $resource['prixRs'];
            $availableStock = (int) $resource['available_stock'];

            $items[] = [
                'resource_id' => $resolvedResourceId,
                'resource_name' => (string) $resource['nomRs'],
                'supplier_name' => (string) ($resource['fournisseur_name'] ?: 'Non renseigne'),
                'status' => (string) $resource['availabilityStatusRs'],
                'price' => $unitPrice,
                'quantity' => $resolvedQuantity,
                'available_stock' => $availableStock,
                'reserved_stock' => (int) $resource['reserved_stock'],
                'total_stock' => (int) $resource['QuantiteRs'],
                'line_total' => $unitPrice * $resolvedQuantity,
                'is_stock_valid' => $resolvedQuantity <= max(0, $availableStock),
            ];
        }

        return $items;
    }

    private function moveReservationToTargetProject(
        Connection $connection,
        int $sourceProjectId,
        int $targetProjectId,
        int $resourceId,
        int $newQuantity,
        ?string $quantityColumn
    ): void {
        if ($quantityColumn !== null) {
            $targetExists = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM project_resources WHERE idProj = ? AND idRs = ?',
                [$targetProjectId, $resourceId]
            );

            if ($targetExists > 0) {
                $connection->executeStatement(
                    'UPDATE project_resources SET ' . $quantityColumn . ' = ' . $quantityColumn . ' + ? WHERE idProj = ? AND idRs = ?',
                    [$newQuantity, $targetProjectId, $resourceId]
                );
                $connection->executeStatement(
                    'DELETE FROM project_resources WHERE idProj = ? AND idRs = ?',
                    [$sourceProjectId, $resourceId]
                );
            } else {
                $connection->executeStatement(
                    'UPDATE project_resources SET idProj = ?, ' . $quantityColumn . ' = ? WHERE idProj = ? AND idRs = ?',
                    [$targetProjectId, $newQuantity, $sourceProjectId, $resourceId]
                );
            }

            return;
        }

        $connection->executeStatement(
            'DELETE FROM project_resources WHERE idProj = ? AND idRs = ?',
            [$sourceProjectId, $resourceId]
        );

        $this->insertUnitRows($connection, $targetProjectId, $resourceId, $newQuantity);
    }

    private function assertClient(User $client): void
    {
        if (strtolower((string) $client->getRoleUser()) !== 'client') {
            throw new \InvalidArgumentException('Le mini-shop est reserve au role CLIENT.');
        }
    }

    private function resolveProjectId(Connection $connection, User $client, ?int $projectId): int
    {
        if ($projectId !== null && $projectId > 0) {
            return $this->resolveOwnedProject($connection, $client, $projectId);
        }

        $latestProjectId = (int) $connection->fetchOne(
            'SELECT idProj FROM projects WHERE idClient = ? ORDER BY idProj DESC LIMIT 1',
            [(int) $client->getIdUser()]
        );

        if ($latestProjectId > 0) {
            return $latestProjectId;
        }

        // Creation automatique du projet de reservation, comme dans la logique Java attendue.
        $connection->insert('projects', [
            'titleProj' => 'Reservation Ressources',
            'descriptionProj' => 'Cree automatiquement pour reservation',
            'budgetProj' => 0.0,
            'typeProj' => 'RESOURCE',
            'stateProj' => 'PENDING',
            'createdAtProj' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updatedAtProj' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'avancementProj' => 0.0,
            'idClient' => (int) $client->getIdUser(),
        ]);

        return (int) $connection->lastInsertId();
    }

    private function resolveOwnedProject(Connection $connection, User $client, int $projectId): int
    {
        $resolvedProjectId = (int) $connection->fetchOne(
            'SELECT idProj FROM projects WHERE idProj = ? AND idClient = ?',
            [$projectId, (int) $client->getIdUser()]
        );

        if ($resolvedProjectId <= 0) {
            throw new \InvalidArgumentException('Projet client introuvable.');
        }

        return $resolvedProjectId;
    }

    /**
     * @param array<int, int> $projectIds
     */
    private function lockOwnedProjectsForUpdate(Connection $connection, int $clientId, array $projectIds): void
    {
        $normalizedProjectIds = [];
        foreach ($projectIds as $projectId) {
            $resolvedProjectId = (int) $projectId;
            if ($resolvedProjectId > 0) {
                $normalizedProjectIds[$resolvedProjectId] = $resolvedProjectId;
            }
        }

        if ($normalizedProjectIds === []) {
            throw new \InvalidArgumentException('Projet client introuvable.');
        }

        $orderedProjectIds = array_values($normalizedProjectIds);
        sort($orderedProjectIds);

        foreach ($orderedProjectIds as $projectId) {
            $lockedProjectId = (int) $connection->fetchOne(
                'SELECT idProj FROM projects WHERE idProj = ? AND idClient = ? FOR UPDATE',
                [$projectId, $clientId]
            );

            if ($lockedProjectId <= 0) {
                throw new \InvalidArgumentException('Projet client introuvable.');
            }
        }
    }

    private function listedRemainingForProjectResource(
        Connection $connection,
        int $clientId,
        int $projectId,
        int $resourceId
    ): int {
        return (int) $connection->fetchOne(
            'SELECT COALESCE(SUM(qtyRemaining), 0)
             FROM resource_market_listing
             WHERE sellerUserId = ? AND idProj = ? AND idRs = ? AND status = ?',
            [$clientId, $projectId, $resourceId, self::LISTING_LISTED]
        );
    }

    private function getReservedStock(Connection $connection, int $resourceId): int
    {
        return (int) $connection->fetchOne(
            'SELECT ' . $this->quantityAggregateSql($connection, 'pr') . ' FROM project_resources pr WHERE pr.idRs = ?',
            [$resourceId]
        );
    }

    private function getOwnedReservationQuantity(Connection $connection, User $client, int $projectId, int $resourceId): int
    {
        return (int) $connection->fetchOne(
            '
            SELECT ' . $this->quantityAggregateSql($connection, 'pr') . '
            FROM project_resources pr
            INNER JOIN projects p ON p.idProj = pr.idProj
            WHERE pr.idProj = ? AND pr.idRs = ? AND p.idClient = ?
            ',
            [$projectId, $resourceId, (int) $client->getIdUser()]
        );
    }

    /**
     * Sans colonne quantite, on reproduit le mode legacy: une ligne par unite reservee.
     */
    private function insertUnitRows(Connection $connection, int $projectId, int $resourceId, int $quantity): void
    {
        for ($index = 0; $index < $quantity; $index++) {
            $connection->insert('project_resources', [
                'idProj' => $projectId,
                'idRs' => $resourceId,
            ]);
        }
    }

    private function deleteUnitRows(Connection $connection, int $projectId, int $resourceId, int $quantity): void
    {
        $safeQuantity = max(0, $quantity);
        if ($safeQuantity === 0) {
            return;
        }

        $connection->executeStatement(
            sprintf('DELETE FROM project_resources WHERE idProj = ? AND idRs = ? LIMIT %d', $safeQuantity),
            [$projectId, $resourceId]
        );
    }

    private function quantityAggregateSql(Connection $connection, string $alias): string
    {
        $quantityColumn = $this->detectQuantityColumn($connection);

        if ($quantityColumn !== null) {
            return 'SUM(COALESCE(' . $alias . '.' . $quantityColumn . ', 1))';
        }

        return 'COUNT(*)';
    }

    /**
     * On detecte uniquement une colonne existante. On ne modifie jamais le schema MySQL.
     */
    private function detectQuantityColumn(Connection $connection): ?string
    {
        if ($this->quantityColumnDetected) {
            return $this->quantityColumnCache;
        }

        $this->quantityColumnDetected = true;

        $columns = $connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_resources'"
        );

        foreach ($columns as $column) {
            $normalized = strtolower((string) $column);
            if (in_array($normalized, ['quantite', 'quantity', 'qty', 'qtyallocated'], true)) {
                return $this->quantityColumnCache = (string) $column;
            }
        }

        return null;
    }
}
