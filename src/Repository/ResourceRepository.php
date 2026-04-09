<?php

namespace App\Repository;

use App\Entity\Resource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<resource>
 */
class ResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resource::class);
    }

    public function findFrontResources(array $filters = []): array
    {
        $filters = array_merge([
            'q' => '',
            'status' => '',
            'supplier_id' => '',
            'min_price' => null,
            'max_price' => null,
        ], $filters);

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.cataloguefournisseur', 'c')
            ->addSelect('c')
            ->orderBy('r.idRs', 'DESC');

        if (!empty($filters['q'])) {
            $search = '%' . mb_strtolower(trim((string) $filters['q'])) . '%';
            $qb->andWhere('
                LOWER(COALESCE(r.nomRs, \'\')) LIKE :q
                OR LOWER(COALESCE(r.availabilityStatusRs, \'\')) LIKE :q
                OR LOWER(COALESCE(c.nomFr, \'\')) LIKE :q
                OR LOWER(COALESCE(c.fournisseur, \'\')) LIKE :q
                OR CONCAT(\'\', r.idRs) LIKE :q
            ')
                ->setParameter('q', $search);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('r.availabilityStatusRs = :status')
                ->setParameter('status', trim((string) $filters['status']));
        }

        if (!empty($filters['supplier_id'])) {
            $qb->andWhere('c.idFr = :supplierId')
                ->setParameter('supplierId', (int) $filters['supplier_id']);
        }

        if ($filters['min_price'] !== null && $filters['min_price'] !== '') {
            $qb->andWhere('r.prixRs >= :minPrice')
                ->setParameter('minPrice', (float) $filters['min_price']);
        }

        if ($filters['max_price'] !== null && $filters['max_price'] !== '') {
            $qb->andWhere('r.prixRs <= :maxPrice')
                ->setParameter('maxPrice', (float) $filters['max_price']);
        }

        return $qb->getQuery()->getResult();
    }

    public function findBackOfficeResources(array $filters = []): array
    {
        return $this->findFrontResources($filters);
    }

    public function findOneWithSupplier(int $id): ?Resource
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.cataloguefournisseur', 'c')
            ->addSelect('c')
            ->andWhere('r.idRs = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
