<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return Booking[]
     */
    public function findClientBookings(User $user): array
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.bookingDate', 'DESC')
            ->addOrderBy('b.idBk', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Booking[]
     */
    public function findAllDetailed(array $filters = []): array
    {
        $qb = $this->createDetailedQueryBuilder()
            ->orderBy('b.bookingDate', 'DESC')
            ->addOrderBy('b.idBk', 'DESC');

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $qb->andWhere(
                'e.titleEv LIKE :q
                OR e.organisateurName LIKE :q
                OR CONCAT(COALESCE(u.PrenomUser, \'\'), \' \', COALESCE(u.nomUser, \'\')) LIKE :q
                OR u.EmailUser LIKE :q'
            )->setParameter('q', '%' . $q . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findDetailedById(int $id): ?Booking
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('b.idBk = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOwnedBookingWithRelations(int $id, User $user): ?Booking
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('b.idBk = :id')
            ->andWhere('b.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByUserAndEvent(User $user, Event $event, ?int $excludeId = null): ?Booking
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->andWhere('b.event = :event')
            ->setParameter('user', $user)
            ->setParameter('event', $event)
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $qb->andWhere('b.idBk != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function countEventBookings(Event $event): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.idBk)')
            ->andWhere('b.event = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countReservedTicketsForEvent(Event $event, ?Booking $excludedBooking = null): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.numTicketBk), 0)')
            ->andWhere('b.event = :event')
            ->setParameter('event', $event);

        if ($excludedBooking instanceof Booking && $excludedBooking->getIdBk() !== null) {
            $qb->andWhere('b.idBk != :excludedId')
                ->setParameter('excludedId', $excludedBooking->getIdBk());
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function createDetailedQueryBuilder()
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.event', 'e')
            ->addSelect('e')
            ->leftJoin('e.user', 'manager')
            ->addSelect('manager')
            ->leftJoin('b.user', 'u')
            ->addSelect('u');
    }
}
