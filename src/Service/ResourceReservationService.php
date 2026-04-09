<?php

namespace App\Service;

use App\Entity\Resource;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class ResourceReservationService
{
    private ?string $quantityColumnCache = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getReservedStock(int $resourceId): int
    {
        $connection = $this->entityManager->getConnection();
        $quantityColumn = $this->detectQuantityColumn($connection);

        if ($quantityColumn !== null) {
            return (int) $connection->fetchOne(
                'SELECT COALESCE(SUM(' . $quantityColumn . '), 0) FROM project_resources WHERE idRs = ?',
                [$resourceId]
            );
        }

        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM project_resources WHERE idRs = ?',
            [$resourceId]
        );
    }

    public function getAvailableStock(Resource $resource): int
    {
        $total = max(0, (int) ($resource->getQuantity() ?? 0));
        $reserved = $resource->getId() ? $this->getReservedStock((int) $resource->getId()) : 0;

        return max(0, $total - $reserved);
    }

    public function reserveForClient(User $user, Resource $resource, int $quantity, ?int $projectIdOrNull): int
    {
        if (strtolower((string) $user->getRoleUser()) !== 'client') {
            throw new \InvalidArgumentException('Seul un client peut reserver une ressource.');
        }

        if (($resource->getId() ?? 0) <= 0) {
            throw new \InvalidArgumentException('Ressource invalide.');
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantite > 0 obligatoire.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $snapshot = $this->getResourceStockSnapshot($connection, (int) $resource->getId());
            if ($snapshot['total'] <= 0 || $snapshot['status'] === Resource::STATUS_UNAVAILABLE) {
                throw new \InvalidArgumentException('Cette ressource est indisponible.');
            }

            if ($quantity > $snapshot['available']) {
                throw new \InvalidArgumentException('Stock insuffisant. Disponible: ' . $snapshot['available']);
            }

            $projectId = $this->resolveOrCreateProject($connection, $user, $projectIdOrNull);
            $this->insertProjectResourceLink($connection, $projectId, (int) $resource->getId(), $quantity);
            $this->syncResourceAvailabilityStatus($connection, (int) $resource->getId());
            $connection->commit();

            return $projectId;
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    public function updateClientReservation(User $user, int $projectId, int $resourceId, int $quantity, ?int $targetProjectIdOrNull): int
    {
        if (strtolower((string) $user->getRoleUser()) !== 'client') {
            throw new \InvalidArgumentException('Seul un client peut modifier une reservation.');
        }

        return $this->updateReservation($projectId, $resourceId, $quantity, $targetProjectIdOrNull, (int) $user->getIdUser(), false);
    }

    public function updateReservationForManager(int $projectId, int $resourceId, int $quantity, ?int $targetProjectIdOrNull): int
    {
        return $this->updateReservation($projectId, $resourceId, $quantity, $targetProjectIdOrNull, null, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClientReservations(User $user): array
    {
        if (strtolower((string) $user->getRoleUser()) !== 'client') {
            return [];
        }

        $connection = $this->entityManager->getConnection();
        $quantityColumn = $this->detectQuantityColumn($connection);
        $quantitySelect = $quantityColumn !== null
            ? 'COALESCE(pr.' . $quantityColumn . ', 1) AS reserved_qty'
            : '1 AS reserved_qty';

        return $connection->fetchAllAssociative(
            'SELECT 
                pr.idProj AS project_id,
                pr.idRs AS resource_id,
                p.titleProj AS project_title,
                p.stateProj AS project_status,
                r.nomRs AS resource_name,
                r.availabilityStatusRs AS resource_status,
                r.QuantiteRs AS resource_stock,
                r.prixRs AS resource_price,
                cf.nomFr AS supplier_name,
                cf.fournisseur AS supplier_company,
                ' . $quantitySelect . '
             FROM project_resources pr
             INNER JOIN projects p ON p.idProj = pr.idProj
             INNER JOIN resources r ON r.idRs = pr.idRs
             LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
             WHERE p.idClient = ?
             ORDER BY p.idProj DESC, pr.idRs DESC',
            [(int) $user->getIdUser()]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllReservations(): array
    {
        $connection = $this->entityManager->getConnection();
        $quantityColumn = $this->detectQuantityColumn($connection);
        $quantitySelect = $quantityColumn !== null
            ? 'COALESCE(pr.' . $quantityColumn . ', 1) AS reserved_qty'
            : '1 AS reserved_qty';

        return $connection->fetchAllAssociative(
            'SELECT 
                pr.idProj AS project_id,
                pr.idRs AS resource_id,
                p.titleProj AS project_title,
                p.stateProj AS project_status,
                p.idClient AS client_id,
                u.nomUser AS client_lastname,
                u.PrenomUser AS client_firstname,
                u.EmailUser AS client_email,
                r.nomRs AS resource_name,
                r.availabilityStatusRs AS resource_status,
                r.QuantiteRs AS resource_stock,
                r.prixRs AS resource_price,
                cf.nomFr AS supplier_name,
                cf.fournisseur AS supplier_company,
                ' . $quantitySelect . '
             FROM project_resources pr
             INNER JOIN projects p ON p.idProj = pr.idProj
             INNER JOIN `user` u ON u.idUser = p.idClient
             INNER JOIN resources r ON r.idRs = pr.idRs
             LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
             ORDER BY p.idProj DESC, pr.idRs DESC'
        );
    }

    public function getClientReservation(User $user, int $projectId, int $resourceId): ?array
    {
        return $this->getReservationRow($projectId, $resourceId, (int) $user->getIdUser(), false);
    }

    public function getReservationForManager(int $projectId, int $resourceId): ?array
    {
        return $this->getReservationRow($projectId, $resourceId, null, true);
    }

    public function deleteClientReservation(User $user, int $projectId, int $resourceId): void
    {
        if (strtolower((string) $user->getRoleUser()) !== 'client') {
            throw new \InvalidArgumentException('Seul un client peut supprimer une reservation.');
        }

        $this->deleteReservation($projectId, $resourceId, (int) $user->getIdUser(), false);
    }

    public function deleteReservationForManager(int $projectId, int $resourceId): void
    {
        $this->deleteReservation($projectId, $resourceId, null, true);
    }

    private function deleteReservation(int $projectId, int $resourceId, ?int $clientId, bool $allowAll): void
    {
        $connection = $this->entityManager->getConnection();
        $row = $this->getReservationRow($projectId, $resourceId, $clientId, $allowAll);
        if ($row === null) {
            throw new \InvalidArgumentException('Reservation introuvable pour cet utilisateur.');
        }

        $connection->beginTransaction();

        try {
            $deleted = $connection->executeStatement(
                'DELETE FROM project_resources WHERE idProj = ? AND idRs = ?',
                [$projectId, $resourceId]
            );

            if ($deleted <= 0) {
                throw new \InvalidArgumentException('Reservation introuvable ou deja supprimee.');
            }

            $this->syncResourceAvailabilityStatus($connection, $resourceId);
            $connection->commit();
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    private function updateReservation(int $projectId, int $resourceId, int $quantity, ?int $targetProjectIdOrNull, ?int $clientId, bool $allowAll): int
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantite > 0 obligatoire.');
        }

        $connection = $this->entityManager->getConnection();
        $row = $this->getReservationRow($projectId, $resourceId, $clientId, $allowAll);
        if ($row === null) {
            throw new \InvalidArgumentException('Reservation introuvable.');
        }

        $targetProjectId = $targetProjectIdOrNull !== null && $targetProjectIdOrNull > 0
            ? $this->resolveTargetProjectForUpdate($connection, $targetProjectIdOrNull, $clientId, $allowAll)
            : $projectId;

        $resourceStock = (int) ($row['resource_stock'] ?? 0);
        $currentQty = (int) ($row['reserved_qty'] ?? 1);
        $totalReserved = $this->getReservedStock($resourceId);
        $availableExcludingCurrent = max(0, $resourceStock - ($totalReserved - $currentQty));
        $editableMax = max($currentQty, $availableExcludingCurrent);

        if ($quantity > $editableMax) {
            throw new \InvalidArgumentException('Stock insuffisant. Disponible pour modification: ' . $editableMax);
        }

        $quantityColumn = $this->detectQuantityColumn($connection);
        $targetExists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM project_resources WHERE idProj = ? AND idRs = ?',
            [$targetProjectId, $resourceId]
        ) > 0;

        if (($quantity > 1 || ($targetProjectId !== $projectId && $targetExists)) && $quantityColumn === null) {
            $this->ensureQuantityColumnExists($connection);
            $quantityColumn = $this->detectQuantityColumn($connection);
        }

        $connection->beginTransaction();

        try {

            if ($targetProjectId === $projectId) {
                if ($quantityColumn !== null) {
                    $connection->executeStatement(
                        'UPDATE project_resources SET ' . $quantityColumn . ' = ? WHERE idProj = ? AND idRs = ?',
                        [$quantity, $projectId, $resourceId]
                    );
                }

                $this->syncResourceAvailabilityStatus($connection, $resourceId);

                $connection->commit();

                return $projectId;
            }

            if ($quantityColumn !== null) {
                if ($targetExists) {
                    $connection->executeStatement(
                        'UPDATE project_resources SET ' . $quantityColumn . ' = ' . $quantityColumn . ' + ? WHERE idProj = ? AND idRs = ?',
                        [$quantity, $targetProjectId, $resourceId]
                    );
                    $connection->executeStatement(
                        'DELETE FROM project_resources WHERE idProj = ? AND idRs = ?',
                        [$projectId, $resourceId]
                    );
                } else {
                    $connection->executeStatement(
                        'UPDATE project_resources SET idProj = ?, ' . $quantityColumn . ' = ? WHERE idProj = ? AND idRs = ?',
                        [$targetProjectId, $quantity, $projectId, $resourceId]
                    );
                }
            } else {
                if ($targetExists) {
                    throw new \InvalidArgumentException('Cette ressource est deja reservee sur le projet cible.');
                }

                $connection->executeStatement(
                    'UPDATE project_resources SET idProj = ? WHERE idProj = ? AND idRs = ?',
                    [$targetProjectId, $projectId, $resourceId]
                );
            }

            $this->syncResourceAvailabilityStatus($connection, $resourceId);

            $connection->commit();

            return $targetProjectId;
        } catch (\Throwable $throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    private function resolveOrCreateProject(Connection $connection, User $user, ?int $projectIdOrNull): int
    {
        $clientId = (int) $user->getIdUser();
        if ($clientId <= 0) {
            throw new \InvalidArgumentException('Client invalide.');
        }

        if ($projectIdOrNull !== null && $projectIdOrNull > 0) {
            $projectId = (int) $connection->fetchOne(
                'SELECT idProj FROM projects WHERE idProj = ? AND idClient = ?',
                [$projectIdOrNull, $clientId]
            );

            if ($projectId > 0) {
                return $projectId;
            }

            throw new \InvalidArgumentException('Projet client introuvable.');
        }

        $latestProjectId = (int) $connection->fetchOne(
            'SELECT idProj FROM projects WHERE idClient = ? ORDER BY idProj DESC LIMIT 1',
            [$clientId]
        );

        if ($latestProjectId > 0) {
            return $latestProjectId;
        }

        $connection->insert('projects', [
            'titleProj' => 'Reservation Ressources',
            'descriptionProj' => 'Cree automatiquement pour reservation',
            'budgetProj' => 0,
            'typeProj' => 'RESOURCE',
            'stateProj' => 'PENDING',
            'createdAtProj' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updatedAtProj' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'avancementProj' => 0,
            'idClient' => $clientId,
        ]);

        return (int) $connection->lastInsertId();
    }

    private function resolveTargetProjectForUpdate(Connection $connection, int $projectId, ?int $clientId, bool $allowAll): int
    {
        if ($allowAll) {
            $resolved = (int) $connection->fetchOne(
                'SELECT idProj FROM projects WHERE idProj = ?',
                [$projectId]
            );

            if ($resolved > 0) {
                return $resolved;
            }

            throw new \InvalidArgumentException('Projet cible introuvable.');
        }

        $resolved = (int) $connection->fetchOne(
            'SELECT idProj FROM projects WHERE idProj = ? AND idClient = ?',
            [$projectId, (int) $clientId]
        );

        if ($resolved > 0) {
            return $resolved;
        }

        throw new \InvalidArgumentException('Projet client cible introuvable.');
    }

    private function insertProjectResourceLink(Connection $connection, int $projectId, int $resourceId, int $quantity): void
    {
        $quantityColumn = $this->detectQuantityColumn($connection);
        $existing = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM project_resources WHERE idProj = ? AND idRs = ?',
            [$projectId, $resourceId]
        );

        if (($quantity > 1 || $existing > 0) && $quantityColumn === null) {
            $this->ensureQuantityColumnExists($connection);
            $quantityColumn = $this->detectQuantityColumn($connection);
        }

        if ($quantityColumn !== null) {
            if ($existing > 0) {
                $connection->executeStatement(
                    'UPDATE project_resources SET ' . $quantityColumn . ' = ' . $quantityColumn . ' + ? WHERE idProj = ? AND idRs = ?',
                    [$quantity, $projectId, $resourceId]
                );

                return;
            }

            $connection->insert('project_resources', [
                'idProj' => $projectId,
                'idRs' => $resourceId,
                $quantityColumn => $quantity,
            ]);

            return;
        }

        if ($existing > 0) {
            throw new \InvalidArgumentException('Cette ressource est deja liee a ce projet.');
        }

        $connection->insert('project_resources', [
            'idProj' => $projectId,
            'idRs' => $resourceId,
        ]);
    }

    private function ensureQuantityColumnExists(Connection $connection): void
    {
        if ($this->detectQuantityColumn($connection) !== null) {
            return;
        }

        $connection->executeStatement('ALTER TABLE project_resources ADD COLUMN qtyAllocated INT NOT NULL DEFAULT 1');
        $this->quantityColumnCache = 'qtyAllocated';
    }

    private function detectQuantityColumn(Connection $connection): ?string
    {
        if ($this->quantityColumnCache !== null) {
            return $this->quantityColumnCache;
        }

        $columns = $connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_resources'"
        );

        foreach ($columns as $column) {
            $normalized = strtolower((string) $column);
            if (in_array($normalized, ['qtyallocated', 'quantity', 'qty'], true)) {
                return $this->quantityColumnCache = (string) $column;
            }
        }

        return null;
    }

    private function getReservationRow(int $projectId, int $resourceId, ?int $clientId, bool $allowAll): ?array
    {
        $connection = $this->entityManager->getConnection();
        $quantityColumn = $this->detectQuantityColumn($connection);
        $quantitySelect = $quantityColumn !== null
            ? 'COALESCE(pr.' . $quantityColumn . ', 1) AS reserved_qty'
            : '1 AS reserved_qty';

        $sql = 'SELECT 
                    pr.idProj AS project_id,
                    pr.idRs AS resource_id,
                    p.titleProj AS project_title,
                    p.stateProj AS project_status,
                    p.idClient AS client_id,
                    u.nomUser AS client_lastname,
                    u.PrenomUser AS client_firstname,
                    u.EmailUser AS client_email,
                    r.nomRs AS resource_name,
                    r.availabilityStatusRs AS resource_status,
                    r.QuantiteRs AS resource_stock,
                    r.prixRs AS resource_price,
                    cf.nomFr AS supplier_name,
                    cf.fournisseur AS supplier_company,
                    ' . $quantitySelect . '
                FROM project_resources pr
                INNER JOIN projects p ON p.idProj = pr.idProj
                INNER JOIN `user` u ON u.idUser = p.idClient
                INNER JOIN resources r ON r.idRs = pr.idRs
                LEFT JOIN cataloguefournisseur cf ON cf.idFr = r.idFr
                WHERE pr.idProj = ? AND pr.idRs = ?';

        $params = [$projectId, $resourceId];
        if (!$allowAll) {
            $sql .= ' AND p.idClient = ?';
            $params[] = (int) $clientId;
        }

        $row = $connection->fetchAssociative($sql, $params) ?: null;
        if ($row === null) {
            return null;
        }

        $resourceId = (int) ($row['resource_id'] ?? 0);
        $resourceStock = max(0, (int) ($row['resource_stock'] ?? 0));
        $currentQty = max(1, (int) ($row['reserved_qty'] ?? 1));
        $reservedTotal = $resourceId > 0 ? $this->getReservedStock($resourceId) : 0;
        $availableStock = max(0, $resourceStock - $reservedTotal);
        $availableExcludingCurrent = max(0, $resourceStock - ($reservedTotal - $currentQty));

        $row['resource_reserved_total'] = $reservedTotal;
        $row['resource_available_stock'] = $availableStock;
        $row['resource_editable_max'] = max($currentQty, $availableExcludingCurrent);

        return $row;
    }

    /**
     * @return array{total:int,reserved:int,available:int,status:string}
     */
    private function getResourceStockSnapshot(Connection $connection, int $resourceId): array
    {
        $row = $connection->fetchAssociative(
            'SELECT QuantiteRs AS resource_stock, availabilityStatusRs AS resource_status FROM resources WHERE idRs = ?',
            [$resourceId]
        );

        if ($row === false) {
            throw new \InvalidArgumentException('Ressource introuvable.');
        }

        $total = max(0, (int) ($row['resource_stock'] ?? 0));
        $reserved = $this->getReservedStock($resourceId);
        $available = max(0, $total - $reserved);
        $status = $this->resolveEffectiveStatus($total, $available);

        return [
            'total' => $total,
            'reserved' => $reserved,
            'available' => $available,
            'status' => $status,
        ];
    }

    private function syncResourceAvailabilityStatus(Connection $connection, int $resourceId): void
    {
        $snapshot = $this->getResourceStockSnapshot($connection, $resourceId);

        $connection->executeStatement(
            'UPDATE resources SET availabilityStatusRs = ? WHERE idRs = ?',
            [$snapshot['status'], $resourceId]
        );
    }

    private function resolveEffectiveStatus(int $total, int $available): string
    {
        if ($total <= 0) {
            return Resource::STATUS_UNAVAILABLE;
        }

        if ($available <= 0) {
            return Resource::STATUS_RESERVED;
        }

        return Resource::STATUS_AVAILABLE;
    }
}
