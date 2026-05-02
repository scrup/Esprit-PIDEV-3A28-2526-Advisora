<?php

namespace App\Repository;

use App\Entity\Investment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * @param array<string, mixed> $filters
     *
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
     * @param array<string, mixed> $filters
     *
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

    private function createDetailedQueryBuilder(): QueryBuilder
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

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(QueryBuilder $qb, array $filters, bool $clientScope): void
    {
        $query = trim((string) ($filters['q'] ?? ''));

        if ($query !== '') {
            $search = '%' . mb_strtolower($query) . '%';
            $searchConditions = $qb->expr()->orX(
                'LOWER(COALESCE(i.commentaireInv, \'\')) LIKE :q',
                'LOWER(COALESCE(i.CurrencyInv, \'\')) LIKE :q',
                'LOWER(COALESCE(p.titleProj, \'\')) LIKE :q'
            );

            if (ctype_digit($query)) {
                $searchConditions->add('i.idInv = :investmentIdExact');
                $searchConditions->add('p.idProj = :projectIdExact');
            }

            $qb->andWhere($searchConditions)
                ->setParameter('q', $search);

            if (ctype_digit($query)) {
                $qb->setParameter('investmentIdExact', (int) $query);
                $qb->setParameter('projectIdExact', (int) $query);
            }
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
