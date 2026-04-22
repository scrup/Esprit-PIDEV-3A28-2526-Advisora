<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return list<Notification>
     */
    public function findUnreadForRecipient(User $recipient): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('recipient', $recipient)
            ->setParameter('isRead', false)
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForRecipient(User $recipient): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('recipient', $recipient)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForRecipient(int $id, User $recipient): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.id = :id')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('id', $id)
            ->setParameter('recipient', $recipient)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
