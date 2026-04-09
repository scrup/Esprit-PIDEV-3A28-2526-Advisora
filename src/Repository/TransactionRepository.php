<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return Transaction[]
     */
    public function findClientTransactions(User $user, array $filters = []): array
    {
        $qb = $this->createDetailedQueryBuilder()
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->orderBy('t.DateTransac', 'DESC')
            ->addOrderBy('t.idTransac', 'DESC');

        $this->applyFilters($qb, $filters, true);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Transaction[]
     */
    public function findBackOfficeTransactions(array $filters = []): array
    {
        $qb = $this->createDetailedQueryBuilder()
            ->orderBy('t.DateTransac', 'DESC')
            ->addOrderBy('t.idTransac', 'DESC');

        $this->applyFilters($qb, $filters, false);

        return $qb->getQuery()->getResult();
    }

    public function findOwnedDetailed(int $id, User $user): ?Transaction
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('t.idTransac = :id')
            ->andWhere('u = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDetailedById(int $id): ?Transaction
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('t.idTransac = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createDetailedQueryBuilder()
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.investment', 'i')
            ->addSelect('i')
            ->leftJoin('i.project', 'p')
            ->addSelect('p')
            ->leftJoin('i.user', 'u')
            ->addSelect('u');
    }

    private function applyFilters($qb, array $filters, bool $clientScope): void
    {
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $search = '%' . mb_strtolower($query) . '%';
            $qb->andWhere(
                'LOWER(COALESCE(t.type, \'\')) LIKE :q
                OR LOWER(COALESCE(t.statut, \'\')) LIKE :q
                OR LOWER(COALESCE(p.titleProj, \'\')) LIKE :q
                OR LOWER(COALESCE(i.commentaireInv, \'\')) LIKE :q
                OR CONCAT(\'\', t.idTransac) LIKE :q
                OR CONCAT(\'\', i.idInv) LIKE :q'
            )->setParameter('q', $search);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('t.statut = :status')
                ->setParameter('status', $status);
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
    //     * @return Transaction[] Returns an array of Transaction objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Transaction
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
