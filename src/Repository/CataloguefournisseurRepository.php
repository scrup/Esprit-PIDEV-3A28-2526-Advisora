<?php

namespace App\Repository;

use App\Entity\Cataloguefournisseur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cataloguefournisseur>
 */
class CataloguefournisseurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cataloguefournisseur::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, Cataloguefournisseur>
     */
    public function findBackOfficeSuppliers(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.resources', 'r')
            ->addSelect('r')
            ->orderBy('c.idFr', 'DESC');

        if (!empty($filters['q'])) {
            $rawSearch = trim((string) $filters['q']);
            $search = '%' . mb_strtolower($rawSearch) . '%';
            $searchConditions = $qb->expr()->orX(
                'LOWER(COALESCE(c.nomFr, \'\')) LIKE :q',
                'LOWER(COALESCE(c.fournisseur, \'\')) LIKE :q',
                'LOWER(COALESCE(c.emailFr, \'\')) LIKE :q',
                'LOWER(COALESCE(c.localisationFr, \'\')) LIKE :q',
                'LOWER(COALESCE(c.numTelFr, \'\')) LIKE :q'
            );

            if (ctype_digit($rawSearch)) {
                $searchConditions->add('c.idFr = :supplierIdExact');
            }

            $qb->andWhere($searchConditions)
                ->setParameter('q', $search);

            if (ctype_digit($rawSearch)) {
                $qb->setParameter('supplierIdExact', (int) $rawSearch);
            }
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === Cataloguefournisseur::STATUS_ACTIVE) {
                $qb->andWhere('c.quantite > 0');
            }

            if ($filters['status'] === Cataloguefournisseur::STATUS_EMPTY) {
                $qb->andWhere('c.quantite <= 0');
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneWithResources(int $id): ?Cataloguefournisseur
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.resources', 'r')
            ->addSelect('r')
            ->andWhere('c.idFr = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.idFr)')
            ->andWhere('LOWER(c.nomFr) = LOWER(:name)')
            ->setParameter('name', trim($name));

        if ($excludeId !== null) {
            $qb->andWhere('c.idFr <> :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
