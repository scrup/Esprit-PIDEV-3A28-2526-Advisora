<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findFrontProjects(array $filters = [], ?User $user = null, bool $canSeeAll = false): array
    {
        if (!$canSeeAll && !$user) {
            return [];
        }

        $projectIds = $this->findFrontProjectIdsBySql($filters, $user, $canSeeAll);

        return $this->loadProjectsByIds($projectIds, true);
    }

    public function findDistinctFrontTypes(?\App\Entity\User $user = null, bool $canSeeAll = false): array
    {
        if (!$canSeeAll && !$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->select('DISTINCT p.typeProj AS type')
            ->andWhere('p.typeProj IS NOT NULL')
            ->andWhere('TRIM(p.typeProj) != \'\'')
            ->orderBy('p.typeProj', 'ASC');

        if (!$canSeeAll && $user) {
            $qb->andWhere('p.user = :user')
                ->setParameter('user', $user);
        }

        $rows = $qb->getQuery()->getArrayResult();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['type'],
            $rows
        ));
    }

    // convenience methods used elsewhere
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAtProj', 'DESC')
            ->addOrderBy('p.idProj', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByOwnerOrdered(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAtProj', 'DESC')
            ->addOrderBy('p.idProj', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBackOfficeProjects(array $filters = []): array
    {
        $projectIds = $this->findBackOfficeProjectIdsBySql($filters);

        return $this->loadProjectsByIds($projectIds);
    }

    private function findFrontProjectIdsBySql(array $filters, ?User $user, bool $canSeeAll): array
    {
        $sql = <<<'SQL'
SELECT p.idProj
FROM projects p
WHERE 1 = 1
SQL;
        $params = [];
        $types = [];

        if (!$canSeeAll && $user instanceof User) {
            $sql .= ' AND p.idClient = :userId';
            $params['userId'] = $user->getIdUser();
        }

        if (!empty($filters['q'])) {
            $sql .= "
 AND (
    LOWER(p.titleProj) LIKE :q
    OR LOWER(COALESCE(p.descriptionProj, '')) LIKE :q
    OR LOWER(COALESCE(p.typeProj, '')) LIKE :q
    OR LOWER(COALESCE(p.stateProj, '')) LIKE :q
    OR CAST(p.idProj AS CHAR) LIKE :q
 )";
            $params['q'] = $this->buildSearchPattern($filters['q']);
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND p.stateProj = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        if (!empty($filters['type'])) {
            $sql .= " AND LOWER(COALESCE(p.typeProj, '')) = :type";
            $params['type'] = mb_strtolower(trim((string) $filters['type']));
        }

        if (($filters['min_price'] ?? null) !== null && ($filters['min_price'] ?? '') !== '') {
            $sql .= ' AND p.budgetProj >= :min';
            $params['min'] = (float) $filters['min_price'];
        }

        if (($filters['max_price'] ?? null) !== null && ($filters['max_price'] ?? '') !== '') {
            $sql .= ' AND p.budgetProj <= :max';
            $params['max'] = (float) $filters['max_price'];
        }

        $sql .= ' ORDER BY p.createdAtProj DESC, p.idProj DESC';

        return $this->fetchProjectIds($sql, $params, $types);
    }

    private function findBackOfficeProjectIdsBySql(array $filters): array
    {
        $sql = <<<'SQL'
SELECT p.idProj
FROM projects p
LEFT JOIN user u ON u.idUser = p.idClient
WHERE 1 = 1
SQL;
        $params = [];
        $types = [];

        if (!empty($filters['q'])) {
            $sql .= "
 AND (
    LOWER(p.titleProj) LIKE :q
    OR LOWER(COALESCE(p.descriptionProj, '')) LIKE :q
    OR LOWER(COALESCE(p.typeProj, '')) LIKE :q
    OR LOWER(COALESCE(p.stateProj, '')) LIKE :q
    OR CAST(p.idProj AS CHAR) LIKE :q
 )";
            $params['q'] = $this->buildSearchPattern($filters['q']);
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND p.stateProj = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        if (!empty($filters['owner'])) {
            $sql .= "
 AND (
    LOWER(COALESCE(u.nomUser, '')) LIKE :owner
    OR LOWER(COALESCE(u.PrenomUser, '')) LIKE :owner
    OR LOWER(COALESCE(u.EmailUser, '')) LIKE :owner
 )";
            $params['owner'] = $this->buildSearchPattern($filters['owner']);
        }

        $sql .= ' ORDER BY p.createdAtProj DESC, p.idProj DESC';

        return $this->fetchProjectIds($sql, $params, $types);
    }

    private function fetchProjectIds(string $sql, array $params = [], array $types = []): array
    {
        $ids = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, $params, $types)
            ->fetchFirstColumn();

        return array_values(array_map('intval', $ids));
    }

    private function loadProjectsByIds(array $projectIds, bool $includeStrategies = false): array
    {
        if ($projectIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->andWhere('p.idProj IN (:ids)')
            ->setParameter('ids', $projectIds, ArrayParameterType::INTEGER);

        if ($includeStrategies) {
            $qb->leftJoin('p.strategies', 's')
                ->addSelect('s');
        }

        $projects = $qb->getQuery()->getResult();
        $projectsById = [];

        foreach ($projects as $project) {
            if ($project instanceof Project && $project->getId() !== null) {
                $projectsById[$project->getId()] = $project;
            }
        }

        $orderedProjects = [];
        foreach ($projectIds as $projectId) {
            if (isset($projectsById[$projectId])) {
                $orderedProjects[] = $projectsById[$projectId];
            }
        }

        return $orderedProjects;
    }

    private function buildSearchPattern(mixed $value): string
    {
        return '%' . mb_strtolower(trim((string) $value)) . '%';
    }

    public function findOneVisibleWithDecisions(int $id, ?\App\Entity\User $user = null, bool $canSeeAll = false): ?Project
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.decisions', 'd')
            ->addSelect('d')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->andWhere('p.idProj = :id')
            ->setParameter('id', $id);

        if (!$canSeeAll) {
            if (!$user) {
                return null;
            }

            $qb->andWhere('p.user = :user')
                ->setParameter('user', $user);
        }

        $qb->orderBy('d.dateDecision', 'DESC')
            ->addOrderBy('d.idD', 'DESC');

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getStatusCounters(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.stateProj AS status, COUNT(p.idProj) AS total')
            ->groupBy('p.stateProj')
            ->getQuery()
            ->getArrayResult();

        $counters = [
            Project::STATUS_PENDING => 0,
            Project::STATUS_ACCEPTED => 0,
            Project::STATUS_REFUSED => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $total = (int) ($row['total'] ?? 0);
            $counters[$status] = $total;
        }

        return $counters;
    }

    public function findLatestProjects(int $limit = 6): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.createdAtProj', 'DESC')
            ->addOrderBy('p.idProj', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function findClientProjectsCreatedAfterId(int $afterProjectId, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->andWhere('p.idProj > :afterProjectId')
            ->andWhere('u.roleUser = :role')
            ->setParameter('afterProjectId', max(0, $afterProjectId))
            ->setParameter('role', 'client')
            ->orderBy('p.idProj', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{accepted: int, refused: int, total: int}
     */
    public function getHistoricalDecisionStats(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.stateProj AS status, COUNT(p.idProj) AS total')
            ->andWhere('p.stateProj IN (:statuses)')
            ->setParameter('statuses', [Project::STATUS_ACCEPTED, Project::STATUS_REFUSED])
            ->groupBy('p.stateProj')
            ->getQuery()
            ->getArrayResult();

        $stats = [
            'accepted' => 0,
            'refused' => 0,
            'total' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $total = (int) ($row['total'] ?? 0);

            if ($status === Project::STATUS_ACCEPTED) {
                $stats['accepted'] = $total;
            }

            if ($status === Project::STATUS_REFUSED) {
                $stats['refused'] = $total;
            }
        }

        $stats['total'] = $stats['accepted'] + $stats['refused'];

        return $stats;
    }

    /**
     * @param list<string> $normalizedTypes
     *
     * @return array<string, array{accepted: int, refused: int, total: int}>
     */
    public function getHistoricalDecisionStatsByTypes(array $normalizedTypes): array
    {
        $normalizedTypes = array_values(array_filter(array_unique($normalizedTypes), static fn (?string $type): bool => $type !== null && $type !== ''));
        if ($normalizedTypes === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select("LOWER(TRIM(COALESCE(p.typeProj, ''))) AS normalizedType", 'p.stateProj AS status', 'COUNT(p.idProj) AS total')
            ->andWhere('p.stateProj IN (:statuses)')
            ->andWhere("LOWER(TRIM(COALESCE(p.typeProj, ''))) IN (:types)")
            ->setParameter('statuses', [Project::STATUS_ACCEPTED, Project::STATUS_REFUSED])
            ->setParameter('types', $normalizedTypes)
            ->groupBy('normalizedType, p.stateProj')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $type = (string) ($row['normalizedType'] ?? '');
            $status = (string) ($row['status'] ?? '');
            $total = (int) ($row['total'] ?? 0);

            if (!isset($stats[$type])) {
                $stats[$type] = [
                    'accepted' => 0,
                    'refused' => 0,
                    'total' => 0,
                ];
            }

            if ($status === Project::STATUS_ACCEPTED) {
                $stats[$type]['accepted'] = $total;
            }

            if ($status === Project::STATUS_REFUSED) {
                $stats[$type]['refused'] = $total;
            }

            $stats[$type]['total'] = $stats[$type]['accepted'] + $stats[$type]['refused'];
        }

        return $stats;
    }

    /**
     * @param list<int> $clientIds
     *
     * @return array<int, array{accepted: int, refused: int, total: int}>
     */
    public function getHistoricalDecisionStatsByClients(array $clientIds): array
    {
        $clientIds = array_values(array_filter(array_unique(array_map('intval', $clientIds)), static fn (int $id): bool => $id > 0));
        if ($clientIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->select('u.idUser AS clientId', 'p.stateProj AS status', 'COUNT(p.idProj) AS total')
            ->andWhere('p.stateProj IN (:statuses)')
            ->andWhere('u.idUser IN (:clientIds)')
            ->setParameter('statuses', [Project::STATUS_ACCEPTED, Project::STATUS_REFUSED])
            ->setParameter('clientIds', $clientIds)
            ->groupBy('u.idUser, p.stateProj')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $clientId = (int) ($row['clientId'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $total = (int) ($row['total'] ?? 0);

            if ($clientId <= 0) {
                continue;
            }

            if (!isset($stats[$clientId])) {
                $stats[$clientId] = [
                    'accepted' => 0,
                    'refused' => 0,
                    'total' => 0,
                ];
            }

            if ($status === Project::STATUS_ACCEPTED) {
                $stats[$clientId]['accepted'] = $total;
            }

            if ($status === Project::STATUS_REFUSED) {
                $stats[$clientId]['refused'] = $total;
            }

            $stats[$clientId]['total'] = $stats[$clientId]['accepted'] + $stats[$clientId]['refused'];
        }

        return $stats;
    }

    /**
     * @param list<string> $normalizedTypes
     *
     * @return array<string, list<float>>
     */
    public function getAcceptedBudgetsByTypes(array $normalizedTypes): array
    {
        $normalizedTypes = array_values(array_filter(array_unique($normalizedTypes), static fn (?string $type): bool => $type !== null && $type !== ''));
        if ($normalizedTypes === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select("LOWER(TRIM(COALESCE(p.typeProj, ''))) AS normalizedType", 'p.budgetProj AS budget')
            ->andWhere('p.stateProj = :accepted')
            ->andWhere('p.budgetProj > 0')
            ->andWhere("LOWER(TRIM(COALESCE(p.typeProj, ''))) IN (:types)")
            ->setParameter('accepted', Project::STATUS_ACCEPTED)
            ->setParameter('types', $normalizedTypes)
            ->orderBy('normalizedType', 'ASC')
            ->addOrderBy('p.budgetProj', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $budgets = [];
        foreach ($rows as $row) {
            $type = (string) ($row['normalizedType'] ?? '');
            $budget = (float) ($row['budget'] ?? 0);
            if ($type === '' || $budget <= 0) {
                continue;
            }

            $budgets[$type] ??= [];
            $budgets[$type][] = $budget;
        }

        return $budgets;
    }

    /**
     * @return list<float>
     */
    public function getAcceptedGlobalBudgets(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.budgetProj AS budget')
            ->andWhere('p.stateProj = :accepted')
            ->andWhere('p.budgetProj > 0')
            ->setParameter('accepted', Project::STATUS_ACCEPTED)
            ->orderBy('p.budgetProj', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn (array $row): float => (float) ($row['budget'] ?? 0),
            array_filter($rows, static fn (array $row): bool => (float) ($row['budget'] ?? 0) > 0)
        ));
    }

    /**
     * @param list<Project> $projects
     *
     * @return array{PENDING: int, ACCEPTED: int, REFUSED: int}
     */
    public function getScopedStatusCounters(array $projects): array
    {
        $counters = [
            Project::STATUS_PENDING => 0,
            Project::STATUS_ACCEPTED => 0,
            Project::STATUS_REFUSED => 0,
        ];

        foreach ($projects as $project) {
            if (!$project instanceof Project) {
                continue;
            }

            $status = $project->getStatus() ?? Project::STATUS_PENDING;
            if (!array_key_exists($status, $counters)) {
                continue;
            }

            ++$counters[$status];
        }

        return $counters;
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<string, int>
     */
    public function getScopedTypeCounters(array $projects, int $limit = 6): array
    {
        $counters = [];

        foreach ($projects as $project) {
            if (!$project instanceof Project) {
                continue;
            }

            $type = trim((string) $project->getLegacyType());
            $label = $type !== '' ? $type : 'Non precise';
            $counters[$label] = ($counters[$label] ?? 0) + 1;
        }

        arsort($counters);

        return array_slice($counters, 0, max(1, $limit), true);
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<string, int>
     */
    public function getScopedMonthlyCreationStats(array $projects, int $months = 6): array
    {
        $months = max(1, $months);
        $referenceDate = new \DateTimeImmutable('first day of this month midnight');
        $labels = [];

        for ($offset = $months - 1; $offset >= 0; --$offset) {
            $month = $referenceDate->modify(sprintf('-%d month', $offset));
            $labels[$month->format('Y-m')] = 0;
        }

        foreach ($projects as $project) {
            if (!$project instanceof Project || !$project->getStartDate() instanceof \DateTimeInterface) {
                continue;
            }

            $key = $project->getStartDate()->format('Y-m');
            if (!array_key_exists($key, $labels)) {
                continue;
            }

            ++$labels[$key];
        }

        return $labels;
    }

    /**
     * @param list<Project> $projects
     *
     * @return array{PENDING: float, ACCEPTED: float, REFUSED: float}
     */
    public function getScopedAverageBudgetsByStatus(array $projects): array
    {
        $totals = [
            Project::STATUS_PENDING => ['sum' => 0.0, 'count' => 0],
            Project::STATUS_ACCEPTED => ['sum' => 0.0, 'count' => 0],
            Project::STATUS_REFUSED => ['sum' => 0.0, 'count' => 0],
        ];

        foreach ($projects as $project) {
            if (!$project instanceof Project) {
                continue;
            }

            $status = $project->getStatus() ?? Project::STATUS_PENDING;
            if (!isset($totals[$status])) {
                continue;
            }

            $budget = (float) ($project->getLegacyBudget() ?? 0.0);
            if ($budget <= 0) {
                continue;
            }

            $totals[$status]['sum'] += $budget;
            ++$totals[$status]['count'];
        }

        $averages = [];
        foreach ($totals as $status => $values) {
            $averages[$status] = $values['count'] > 0
                ? round($values['sum'] / $values['count'], 2)
                : 0.0;
        }

        return $averages;
    }

    //    /**
    //     * @return Project[] Returns an array of Project objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Project
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
