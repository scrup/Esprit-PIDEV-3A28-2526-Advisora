<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * @param array<string, mixed> $filters
     *
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
     * @param array<string, mixed> $filters
     *
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

    private function createBaseListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')
            ->addSelect('u')
            ->leftJoin('e.bookings', 'b')
            ->addSelect('b');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFrontFilters(QueryBuilder $qb, array $filters): void
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

    /**
     * @param array<string, mixed> $filters
     */
    private function applyBackOfficeFilters(QueryBuilder $qb, array $filters): void
    {
        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $qb->andWhere(
                'e.titleEv LIKE :q
                OR e.descriptionEv LIKE :q
                OR e.organisateurName LIKE :q
                OR e.localisationEv LIKE :q
                OR CONCAT(COALESCE(u.PrenomUser, :emptyNamePart), :spaceSeparator, COALESCE(u.nomUser, :emptyNamePart)) LIKE :q
                OR u.EmailUser LIKE :q'
            )
                ->setParameter('q', '%' . $q . '%')
                ->setParameter('emptyNamePart', '')
                ->setParameter('spaceSeparator', ' ');
        }

        $manager = trim((string) ($filters['manager'] ?? ''));

        if ($manager !== '') {
            $qb->andWhere('CONCAT(COALESCE(u.PrenomUser, :emptyNamePart), :spaceSeparator, COALESCE(u.nomUser, :emptyNamePart)) LIKE :manager OR u.EmailUser LIKE :manager')
                ->setParameter('manager', '%' . $manager . '%')
                ->setParameter('emptyNamePart', '')
                ->setParameter('spaceSeparator', ' ');
        }
    }
}
