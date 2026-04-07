<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function findFrontProjects(array $filters = [], ?\App\Entity\User $user = null, bool $canSeeAll = false): array
    {
        if (!$canSeeAll && !$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.createdAtProj', 'DESC')
            ->addOrderBy('p.idProj', 'DESC');

        if (!$canSeeAll && $user) {
            $qb->andWhere('p.user = :user')
                ->setParameter('user', $user);
        }

        if (!empty($filters['q'])) {
            $search = '%' . mb_strtolower(trim((string) $filters['q'])) . '%';
            $qb->andWhere('
                LOWER(p.titleProj) LIKE :q
                OR LOWER(COALESCE(p.descriptionProj, \'\')) LIKE :q
                OR LOWER(COALESCE(p.typeProj, \'\')) LIKE :q
                OR LOWER(COALESCE(p.stateProj, \'\')) LIKE :q
                OR CONCAT(\'\', p.idProj) LIKE :q
            ')
                ->setParameter('q', $search);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.stateProj = :status')
                ->setParameter('status', trim((string) $filters['status']));
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('LOWER(COALESCE(p.typeProj, \'\')) = :type')
                ->setParameter('type', mb_strtolower(trim((string) $filters['type'])));
        }

        if ($filters['min_price'] !== null && $filters['min_price'] !== '') {
            $qb->andWhere('p.budgetProj >= :min')
                ->setParameter('min', (float) $filters['min_price']);
        }

        if ($filters['max_price'] !== null && $filters['max_price'] !== '') {
            $qb->andWhere('p.budgetProj <= :max')
                ->setParameter('max', (float) $filters['max_price']);
        }

        return $qb->getQuery()->getResult();
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
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.createdAtProj', 'DESC')
            ->addOrderBy('p.idProj', 'DESC');

        if (!empty($filters['q'])) {
            $search = '%' . mb_strtolower(trim((string) $filters['q'])) . '%';
            $qb->andWhere('
                LOWER(p.titleProj) LIKE :q
                OR LOWER(COALESCE(p.descriptionProj, \'\')) LIKE :q
                OR LOWER(COALESCE(p.typeProj, \'\')) LIKE :q
                OR LOWER(COALESCE(p.stateProj, \'\')) LIKE :q
                OR CONCAT(\'\', p.idProj) LIKE :q
            ')
                ->setParameter('q', $search);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.stateProj = :status')
                ->setParameter('status', trim((string) $filters['status']));
        }

        if (!empty($filters['owner'])) {
            $owner = '%' . mb_strtolower(trim((string) $filters['owner'])) . '%';
            $qb->andWhere('
                LOWER(COALESCE(u.nomUser, \'\')) LIKE :owner
                OR LOWER(COALESCE(u.PrenomUser, \'\')) LIKE :owner
                OR LOWER(COALESCE(u.EmailUser, \'\')) LIKE :owner
            ')
                ->setParameter('owner', $owner);
        }

        return $qb->getQuery()->getResult();
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
