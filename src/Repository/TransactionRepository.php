<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * @param array<string, mixed> $filters
     *
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
     * @param array<string, mixed> $filters
     *
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

    private function createDetailedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.investment', 'i')
            ->addSelect('i')
            ->leftJoin('i.project', 'p')
            ->addSelect('p')
            ->leftJoin('i.user', 'u')
            ->addSelect('u');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(QueryBuilder $qb, array $filters, bool $clientScope): void
    {
        $query = trim((string) ($filters['q'] ?? ''));

        if ($query !== '') {
            $search = '%' . mb_strtolower($query) . '%';
            $searchConditions = $qb->expr()->orX(
                'LOWER(COALESCE(t.type, \'\')) LIKE :q',
                'LOWER(COALESCE(t.statut, \'\')) LIKE :q',
                'LOWER(COALESCE(p.titleProj, \'\')) LIKE :q',
                'LOWER(COALESCE(i.commentaireInv, \'\')) LIKE :q'
            );

            if (ctype_digit($query)) {
                $searchConditions->add('t.idTransac = :transactionIdExact');
                $searchConditions->add('i.idInv = :investmentIdExact');
            }

            $qb->andWhere($searchConditions)
                ->setParameter('q', $search);

            if (ctype_digit($query)) {
                $qb->setParameter('transactionIdExact', (int) $query);
                $qb->setParameter('investmentIdExact', (int) $query);
            }
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
                $ownerConditions = $qb->expr()->orX(
                    'LOWER(COALESCE(u.nomUser, \'\')) LIKE :owner',
                    'LOWER(COALESCE(u.PrenomUser, \'\')) LIKE :owner',
                    'LOWER(COALESCE(u.EmailUser, \'\')) LIKE :owner'
                );

                if (ctype_digit($owner)) {
                    $ownerConditions->add('u.idUser = :ownerIdExact');
                }

                $qb->andWhere($ownerConditions)
                    ->setParameter('owner', $searchOwner);

                if (ctype_digit($owner)) {
                    $qb->setParameter('ownerIdExact', (int) $owner);
                }
            }
        }
    }
}
