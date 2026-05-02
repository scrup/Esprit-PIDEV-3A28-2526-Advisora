<?php

namespace App\Repository;

use App\Entity\Decision;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Decision>
 */
class DecisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Decision::class);
    }

    /**
     * @return Decision[]
     */
    public function findLatestGlobal(int $limit = 6): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.project', 'p')
            ->addSelect('p')
            ->orderBy('d.dateDecision', 'DESC')
            ->addOrderBy('d.idD', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function findLatestForProject(Project $project): ?Decision
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->setParameter('project', $project)
            ->orderBy('d.dateDecision', 'DESC')
            ->addOrderBy('d.idD', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}