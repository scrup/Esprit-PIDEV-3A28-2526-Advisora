<?php

namespace App\Repository;

use App\Entity\Investment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Investment>
 */
class InvestmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Investment::class);
    }

    /**
     * @return Investment[]
     */
    public function findClientInvestments(User $user, array $filters = []): array
    {
        $qb = $this->createDetailedQueryBuilder()
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('i.idInv', 'DESC');

        $this->applyFilters($qb, $filters, true);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Investment[]
     */
    public function findBackOfficeInvestments(array $filters = []): array
    {
        $qb = $this->createDetailedQueryBuilder()
            ->orderBy('i.idInv', 'DESC');

        $this->applyFilters($qb, $filters, false);

        return $qb->getQuery()->getResult();
    }

    public function findOwnedDetailed(int $id, User $user): ?Investment
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('i.idInv = :id')
            ->andWhere('i.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDetailedById(int $id): ?Investment
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('i.idInv = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createDetailedQueryBuilder()
    {
        return $this->createQueryBuilder('i')
            ->distinct()
            ->leftJoin('i.project', 'p')
            ->addSelect('p')
            ->leftJoin('i.user', 'u')
            ->addSelect('u')
            ->leftJoin('i.transactions', 't')
            ->addSelect('t')
            ->orderBy('i.idInv', 'DESC')
            ->addOrderBy('t.DateTransac', 'DESC')
            ->addOrderBy('t.idTransac', 'DESC');
    }

    private function applyFilters($qb, array $filters, bool $clientScope): void
    {
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $search = '%' . mb_strtolower($query) . '%';
            $qb->andWhere(
                'LOWER(COALESCE(i.commentaireInv, \'\')) LIKE :q
                OR LOWER(COALESCE(i.CurrencyInv, \'\')) LIKE :q
                OR LOWER(COALESCE(p.titleProj, \'\')) LIKE :q
                OR CONCAT(\'\', i.idInv) LIKE :q
                OR CONCAT(\'\', p.idProj) LIKE :q'
            )->setParameter('q', $search);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            if ($status === 'NO_TRANSACTION') {
                $qb->andWhere('t.idTransac IS NULL');
            } else {
                $qb->andWhere('t.statut = :status')
                    ->setParameter('status', $status);
            }
        }

        if (!$clientScope) {
            $owner = trim((string) ($filters['owner'] ?? ''));
            if ($owner !== '') {
                $searchOwner = '%' . mb_strtolower($owner) . '%';
                $qb->andWhere(
                    'LOWER(COALESCE(u.nomUser, \'\')) LIKE :owner
                    OR LOWER(COALESCE(u.PrenomUser, \'\')) LIKE :owner
                    OR LOWER(COALESCE(u.EmailUser, \'\')) LIKE :owner
                    OR CONCAT(\'\', u.idUser) LIKE :owner'
                )->setParameter('owner', $searchOwner);
            }
        }
    }

    //    /**
    //     * @return Investment[] Returns an array of Investment objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Investment
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
