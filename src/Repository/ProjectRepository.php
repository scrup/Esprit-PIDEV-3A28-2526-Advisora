<?php

namespace App\Repository;

use App\Dto\ProjectClientStatusAggregateRow;
use App\Dto\ProjectStatusAggregateRow;
use App\Dto\ProjectTypeStatusAggregateRow;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * @param array<string, mixed> $filters
     *
     * @return Project[]
     */
    public function findFrontProjects(array $filters = [], ?User $user = null, bool $canSeeAll = false): array
    {
        if (!$canSeeAll && !$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->distinct()
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->leftJoin('p.strategies', 's')
            ->addSelect('s')
            ->orderBy('p.createdAtProj', 'DESC')
            ->addOrderBy('p.idProj', 'DESC');

        if (!$canSeeAll) {
            $qb->andWhere('p.user = :user')
                ->setParameter('user', $user);
        }

        if (!empty($filters['q'])) {
            $rawSearch = trim((string) $filters['q']);
            $search = '%' . mb_strtolower($rawSearch) . '%';
            $searchConditions = $qb->expr()->orX(
                'LOWER(p.titleProj) LIKE :q',
                'LOWER(COALESCE(p.descriptionProj, :emptySearchValue)) LIKE :q',
                'LOWER(COALESCE(p.typeProj, :emptySearchValue)) LIKE :q',
                'LOWER(COALESCE(p.stateProj, :emptySearchValue)) LIKE :q'
            );

            if (ctype_digit($rawSearch)) {
                $searchConditions->add('p.idProj = :projectIdExact');
            }

            $qb->andWhere($searchConditions)
                ->setParameter('q', $search)
                ->setParameter('emptySearchValue', '');

            if (ctype_digit($rawSearch)) {
                $qb->setParameter('projectIdExact', (int) $rawSearch);
            }
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.stateProj = :status')
                ->setParameter('status', trim((string) $filters['status']));
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('LOWER(COALESCE(p.typeProj, :emptyTypeValue)) = :type')
                ->setParameter('type', mb_strtolower(trim((string) $filters['type'])))
                ->setParameter('emptyTypeValue', '');
        }

        if (($filters['min_price'] ?? null) !== null && $filters['min_price'] !== '') {
            $qb->andWhere('p.budgetProj >= :min')
                ->setParameter('min', (float) $filters['min_price']);
        }

        if (($filters['max_price'] ?? null) !== null && $filters['max_price'] !== '') {
            $qb->andWhere('p.budgetProj <= :max')
                ->setParameter('max', (float) $filters['max_price']);
        }

        $query = $qb
            ->setMaxResults(12)
            ->getQuery();

        // Avoid partial hydration when limiting a fetch-joined collection.
        return iterator_to_array(new Paginator($query, true));
    }

    /**
     * @return string[]
     */
    public function findDistinctFrontTypes(?User $user = null, bool $canSeeAll = false): array
    {
        if (!$canSeeAll && !$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->select('DISTINCT p.typeProj AS type')
            ->andWhere('p.typeProj IS NOT NULL')
            ->andWhere('TRIM(p.typeProj) != :emptyType')
            ->setParameter('emptyType', '')
            ->orderBy('p.typeProj', 'ASC');

        if (!$canSeeAll) {
            $qb->andWhere('p.user = :user')
                ->setParameter('user', $user);
        }

        $rows = $qb->getQuery()->getArrayResult();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['type'],
            $rows
        ));
    }

    /**
     * @return Project[]
     */
    public function findAllOrdered(int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAtProj', 'DESC')
            ->addOrderBy('p.idProj', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Project[]
     */
    public function findByOwnerOrdered(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAtProj', 'DESC')
            ->addOrderBy('p.idProj', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return Project[]
     */
    public function findBackOfficeProjects(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.createdAtProj', 'DESC')
            ->addOrderBy('p.idProj', 'DESC');

        if (!empty($filters['q'])) {
            $rawSearch = trim((string) $filters['q']);
            $search = '%' . mb_strtolower($rawSearch) . '%';
            $searchConditions = $qb->expr()->orX(
                'LOWER(p.titleProj) LIKE :q',
                'LOWER(COALESCE(p.descriptionProj, :emptySearchValue)) LIKE :q',
                'LOWER(COALESCE(p.typeProj, :emptySearchValue)) LIKE :q',
                'LOWER(COALESCE(p.stateProj, :emptySearchValue)) LIKE :q'
            );

            if (ctype_digit($rawSearch)) {
                $searchConditions->add('p.idProj = :projectIdExact');
            }

            $qb->andWhere($searchConditions)
                ->setParameter('q', $search)
                ->setParameter('emptySearchValue', '');

            if (ctype_digit($rawSearch)) {
                $qb->setParameter('projectIdExact', (int) $rawSearch);
            }
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.stateProj = :status')
                ->setParameter('status', trim((string) $filters['status']));
        }

        if (!empty($filters['owner'])) {
            $owner = '%' . mb_strtolower(trim((string) $filters['owner'])) . '%';
            $qb->andWhere(
                'LOWER(COALESCE(u.nomUser, :emptyOwnerValue)) LIKE :owner
                OR LOWER(COALESCE(u.PrenomUser, :emptyOwnerValue)) LIKE :owner
                OR LOWER(COALESCE(u.EmailUser, :emptyOwnerValue)) LIKE :owner'
            )
                ->setParameter('owner', $owner)
                ->setParameter('emptyOwnerValue', '');
        }

        return $qb
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    public function findOneVisibleWithDecisions(int $id, ?User $user = null, bool $canSeeAll = false): ?Project
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

    /**
     * @return array<string, int>
     */
    public function getStatusCounters(): array
    {
        /** @var list<ProjectStatusAggregateRow> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('NEW App\Dto\ProjectStatusAggregateRow(p.stateProj, COUNT(p.idProj))')
            ->groupBy('p.stateProj')
            ->getQuery()
            ->getResult();

        $counters = [
            Project::STATUS_PENDING => 0,
            Project::STATUS_ACCEPTED => 0,
            Project::STATUS_REFUSED => 0,
        ];

        foreach ($rows as $row) {
            $status = $row->getStatus();
            $total = $row->getTotal();

            if ($status !== '') {
                $counters[$status] = $total;
            }
        }

        return $counters;
    }

    /**
     * @return Project[]
     */
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

    /**
     * @return array{accepted: int, refused: int, total: int}
     */
    public function getHistoricalDecisionStats(): array
    {
        /** @var list<ProjectStatusAggregateRow> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('NEW App\Dto\ProjectStatusAggregateRow(p.stateProj, COUNT(p.idProj))')
            ->andWhere('p.stateProj IN (:statuses)')
            ->setParameter('statuses', [Project::STATUS_ACCEPTED, Project::STATUS_REFUSED])
            ->groupBy('p.stateProj')
            ->getQuery()
            ->getResult();

        $stats = [
            'accepted' => 0,
            'refused' => 0,
            'total' => 0,
        ];

        foreach ($rows as $row) {
            $status = $row->getStatus();
            $total = $row->getTotal();

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
        $normalizedTypes = array_values(array_filter(
            array_unique($normalizedTypes),
            static fn (?string $type): bool => $type !== null && $type !== ''
        ));

        if ($normalizedTypes === []) {
            return [];
        }

        /** @var list<ProjectTypeStatusAggregateRow> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('NEW App\Dto\ProjectTypeStatusAggregateRow(LOWER(TRIM(COALESCE(p.typeProj, :emptyNormalizedType))), p.stateProj, COUNT(p.idProj))')
            ->andWhere('p.stateProj IN (:statuses)')
            ->andWhere('LOWER(TRIM(COALESCE(p.typeProj, :emptyNormalizedType))) IN (:types)')
            ->setParameter('statuses', [Project::STATUS_ACCEPTED, Project::STATUS_REFUSED])
            ->setParameter('emptyNormalizedType', '')
            ->setParameter('types', $normalizedTypes)
            ->groupBy('p.typeProj, p.stateProj')
            ->getQuery()
            ->getResult();

        $stats = [];

        foreach ($rows as $row) {
            $type = $row->getNormalizedType();
            $status = $row->getStatus();
            $total = $row->getTotal();

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
        $clientIds = array_values(array_filter(
            array_unique(array_map('intval', $clientIds)),
            static fn (int $id): bool => $id > 0
        ));

        if ($clientIds === []) {
            return [];
        }

        /** @var list<ProjectClientStatusAggregateRow> $rows */
        $rows = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->select('NEW App\Dto\ProjectClientStatusAggregateRow(u.idUser, p.stateProj, COUNT(p.idProj))')
            ->andWhere('p.stateProj IN (:statuses)')
            ->andWhere('u.idUser IN (:clientIds)')
            ->setParameter('statuses', [Project::STATUS_ACCEPTED, Project::STATUS_REFUSED])
            ->setParameter('clientIds', $clientIds)
            ->groupBy('u.idUser, p.stateProj')
            ->getQuery()
            ->getResult();

        $stats = [];

        foreach ($rows as $row) {
            $clientId = $row->getClientId();
            $status = $row->getStatus();
            $total = $row->getTotal();

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
        $normalizedTypes = array_values(array_filter(
            array_unique($normalizedTypes),
            static fn (?string $type): bool => $type !== null && $type !== ''
        ));

        if ($normalizedTypes === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('LOWER(TRIM(COALESCE(p.typeProj, :emptyNormalizedType))) AS normalizedType', 'p.budgetProj AS budget')
            ->andWhere('p.stateProj = :accepted')
            ->andWhere('p.budgetProj > 0')
            ->andWhere('LOWER(TRIM(COALESCE(p.typeProj, :emptyNormalizedType))) IN (:types)')
            ->setParameter('accepted', Project::STATUS_ACCEPTED)
            ->setParameter('emptyNormalizedType', '')
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
            if (!$project->getStartDate() instanceof \DateTimeInterface) {
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
}
