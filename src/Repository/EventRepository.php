<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @return Event[]
     */
    public function findFrontEvents(array $filters = []): array
    {
        $qb = $this->createBaseListQueryBuilder()
            ->orderBy('e.startDateEv', 'ASC')
            ->addOrderBy('e.idEv', 'DESC');

        $this->applyFrontFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Event[]
     */
    public function findBackOfficeEvents(array $filters = []): array
    {
        $qb = $this->createBaseListQueryBuilder()
            ->orderBy('e.startDateEv', 'DESC')
            ->addOrderBy('e.idEv', 'DESC');

        $this->applyBackOfficeFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    public function findOneWithManagerAndBookings(int $id): ?Event
    {
        return $this->createBaseListQueryBuilder()
            ->leftJoin('b.user', 'bookingUser')
            ->addSelect('bookingUser')
            ->andWhere('e.idEv = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createBaseListQueryBuilder()
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')
            ->addSelect('u')
            ->leftJoin('e.bookings', 'b')
            ->addSelect('b');
    }

    private function applyFrontFilters($qb, array $filters): void
    {
        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $qb->andWhere('e.titleEv LIKE :q OR e.descriptionEv LIKE :q OR e.organisateurName LIKE :q OR e.localisationEv LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        $location = trim((string) ($filters['location'] ?? ''));
        if ($location !== '') {
            $qb->andWhere('e.localisationEv LIKE :location')
                ->setParameter('location', '%' . $location . '%');
        }
    }

    private function applyBackOfficeFilters($qb, array $filters): void
    {
        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $qb->andWhere(
                'e.titleEv LIKE :q
                OR e.descriptionEv LIKE :q
                OR e.organisateurName LIKE :q
                OR e.localisationEv LIKE :q
                OR CONCAT(COALESCE(u.PrenomUser, \'\'), \' \', COALESCE(u.nomUser, \'\')) LIKE :q
                OR u.EmailUser LIKE :q'
            )->setParameter('q', '%' . $q . '%');
        }

        $manager = trim((string) ($filters['manager'] ?? ''));
        if ($manager !== '') {
            $qb->andWhere('CONCAT(COALESCE(u.PrenomUser, \'\'), \' \', COALESCE(u.nomUser, \'\')) LIKE :manager OR u.EmailUser LIKE :manager')
                ->setParameter('manager', '%' . $manager . '%');
        }
    }
}
