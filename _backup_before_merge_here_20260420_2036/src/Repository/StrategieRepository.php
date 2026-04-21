<?php

namespace App\Repository;

use App\Entity\Strategie;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
