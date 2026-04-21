<?php

namespace App\Repository;

use App\Entity\Strategie;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Strategie>
 */
class StrategieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Strategie::class);
    }

    /**
     * @return Strategie[]
     */
    public function findBackOfficeStrategies(array $filters = [], string $sortBy = 'created_at', string $direction = 'DESC'): array
    {
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        $qb = $this->createBackOfficeListQueryBuilder($filters);
        $this->applyBackOfficeSorting($qb, $sortBy, $direction);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return string[]
     */
    public function findAvailableTypes(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.type AS type')
            ->andWhere('s.type IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        $types = [];

        foreach ($rows as $row) {
            $type = mb_strtolower(trim((string) ($row['type'] ?? '')));
            if ($type === '' || in_array($type, $types, true)) {
                continue;
            }

            $types[] = $type;
        }

        sort($types);

        return $types;
    }

    public function getAcceptanceTimeline(): array
    {
        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            '
                SELECT DATE(lockedAt) AS approval_date, COUNT(*) AS total
                FROM strategies
                WHERE statusStrategie = :approved_status
                  AND lockedAt IS NOT NULL
                GROUP BY approval_date
                ORDER BY approval_date ASC
            ',
            [
                'approved_status' => Strategie::STATUS_APPROVED,
            ],
            [
                'approved_status' => Types::STRING,
            ]
        )->fetchAllAssociative();

        $refusedTotal = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.idStrategie)')
            ->andWhere('s.statusStrategie = :rejected_status')
            ->setParameter('rejected_status', Strategie::STATUS_REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        $labels = [];
        $acceptedCounts = [];
        $successRates = [];
        $cumulativeAccepted = 0;

        foreach ($rows as $row) {
            $approvalDate = (string) ($row['approval_date'] ?? '');
            $acceptedCount = (int) ($row['total'] ?? 0);

            if ($approvalDate === '') {
                continue;
            }

            $cumulativeAccepted += $acceptedCount;
            $denominator = $cumulativeAccepted + $refusedTotal;

            $labels[] = $this->formatDateLabel(new \DateTimeImmutable($approvalDate));
            $acceptedCounts[] = $acceptedCount;
            $successRates[] = $denominator > 0
                ? round(($cumulativeAccepted / $denominator) * 100, 1)
                : 0.0;
        }

        return [
            'labels' => $labels,
            'accepted_counts' => $acceptedCounts,
            'success_rates' => $successRates,
            'accepted_total' => $cumulativeAccepted,
            'refused_total' => $refusedTotal,
            'latest_success_rate' => $successRates !== [] ? (float) end($successRates) : 0.0,
        ];
    }

    private function formatDateLabel(\DateTimeImmutable $date): string
    {
        return $date->format('d/m/Y');
    }

    private function createBackOfficeListQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->select('DISTINCT s')
            ->leftJoin('s.project', 'p')
            ->leftJoin('s.user', 'u')
            ->leftJoin('s.objectives', 'o')
            ->addSelect('p', 'u', 'o');

        $searchQuery = trim((string) ($filters['query'] ?? ''));
        if ($searchQuery !== '') {
            $normalizedSearch = '%' . mb_strtolower($searchQuery) . '%';
            $searchConditions = $qb->expr()->orX(
                'LOWER(s.nomStrategie) LIKE :search',
                'LOWER(COALESCE(s.type, \'\')) LIKE :search',
                'LOWER(COALESCE(s.justification, \'\')) LIKE :search',
                'LOWER(COALESCE(s.news, \'\')) LIKE :search',
                'LOWER(COALESCE(p.titleProj, \'\')) LIKE :search',
                'LOWER(COALESCE(p.descriptionProj, \'\')) LIKE :search',
                'LOWER(COALESCE(o.nomObj, \'\')) LIKE :search',
                'LOWER(COALESCE(o.descriptionOb, \'\')) LIKE :search'
            );

            if (ctype_digit($searchQuery)) {
                $searchConditions->add('s.idStrategie = :strategyId');
                $searchConditions->add('p.idProj = :projectId');
                $qb
                    ->setParameter('strategyId', (int) $searchQuery)
                    ->setParameter('projectId', (int) $searchQuery);
            }

            $qb
                ->andWhere($searchConditions)
                ->setParameter('search', $normalizedSearch);
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $qb
                ->andWhere('s.statusStrategie = :status')
                ->setParameter('status', $status);
        }

        $type = mb_strtolower(trim((string) ($filters['type'] ?? '')));
        if ($type !== '') {
            $qb
                ->andWhere('LOWER(s.type) = :type')
                ->setParameter('type', $type);
        }

        return $qb;
    }

    private function applyBackOfficeSorting(QueryBuilder $qb, string $sortBy, string $direction): void
    {
        switch ($sortBy) {
            case 'id':
                $qb->orderBy('s.idStrategie', $direction);
                break;

            case 'name':
                $qb
                    ->addSelect('LOWER(s.nomStrategie) AS HIDDEN nameSort')
                    ->orderBy('nameSort', $direction);
                break;

            case 'project':
                $qb
                    ->addSelect('LOWER(COALESCE(p.titleProj, \'\')) AS HIDDEN projectSort')
                    ->orderBy('projectSort', $direction);
                break;

            case 'status':
                $qb
                    ->addSelect(
                        'CASE
                            WHEN s.statusStrategie = :statusPendingSort THEN 1
                            WHEN s.statusStrategie = :statusInProgressSort THEN 2
                            WHEN s.statusStrategie = :statusApprovedSort THEN 3
                            WHEN s.statusStrategie = :statusRejectedSort THEN 4
                            WHEN s.statusStrategie = :statusUnassignedSort THEN 5
                            ELSE 6
                        END AS HIDDEN statusSort'
                    )
                    ->setParameter('statusPendingSort', Strategie::STATUS_PENDING)
                    ->setParameter('statusInProgressSort', Strategie::STATUS_IN_PROGRESS)
                    ->setParameter('statusApprovedSort', Strategie::STATUS_APPROVED)
                    ->setParameter('statusRejectedSort', Strategie::STATUS_REJECTED)
                    ->setParameter('statusUnassignedSort', Strategie::STATUS_UNASSIGNED)
                    ->orderBy('statusSort', $direction);
                break;

            case 'type':
                $qb
                    ->addSelect('LOWER(COALESCE(s.type, \'\')) AS HIDDEN typeSort')
                    ->orderBy('typeSort', $direction);
                break;

            case 'budget':
                $qb->orderBy('s.budgetTotal', $direction);
                break;

            case 'gain':
                $qb->orderBy('s.gainEstime', $direction);
                break;

            case 'objectives':
                $qb
                    ->addSelect('(SELECT COUNT(objectiveCount.idOb) FROM App\Entity\Objective objectiveCount WHERE objectiveCount.strategie = s) AS HIDDEN objectivesSort')
                    ->orderBy('objectivesSort', $direction);
                break;

            case 'created_at':
            default:
                $qb->orderBy('s.CreatedAtS', $direction);
                break;
        }

        $qb
            ->addOrderBy('s.CreatedAtS', 'DESC')
            ->addOrderBy('s.idStrategie', 'DESC');
    }

    //    /**
    //     * @return Strategie[] Returns an array of Strategie objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Strategie
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
