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

    public function findBackOfficeSuppliers(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.resources', 'r')
            ->addSelect('r')
            ->orderBy('c.idFr', 'DESC');

        if (!empty($filters['q'])) {
            $search = '%' . mb_strtolower(trim((string) $filters['q'])) . '%';
            $qb->andWhere('
                LOWER(COALESCE(c.nomFr, \'\')) LIKE :q
                OR LOWER(COALESCE(c.fournisseur, \'\')) LIKE :q
                OR LOWER(COALESCE(c.emailFr, \'\')) LIKE :q
                OR LOWER(COALESCE(c.localisationFr, \'\')) LIKE :q
                OR LOWER(COALESCE(c.numTelFr, \'\')) LIKE :q
                OR CONCAT(\'\', c.idFr) LIKE :q
            ')
                ->setParameter('q', $search);
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
