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

    /**
     * @return list<Notification>
     */
    public function findLatestForRecipient(User $recipient, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->orderBy('n.isRead', 'ASC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Notification>
     */
    public function findLatestForRole(string $role, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->innerJoin('n.recipient', 'recipient')
            ->andWhere('LOWER(recipient.roleUser) = :role')
            ->setParameter('role', mb_strtolower(trim($role)))
            ->orderBy('n.isRead', 'ASC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForRole(string $role): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->innerJoin('n.recipient', 'recipient')
            ->andWhere('n.isRead = :isRead')
            ->andWhere('LOWER(recipient.roleUser) = :role')
            ->setParameter('isRead', false)
            ->setParameter('role', mb_strtolower(trim($role)))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsUnreadForRecipientAndTitle(User $recipient, string $title): bool
    {
        $count = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :isRead')
            ->andWhere('n.title = :title')
            ->setParameter('recipient', $recipient)
            ->setParameter('isRead', false)
            ->setParameter('title', trim($title))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function existsForRecipientTitleDescriptionOnDate(
        User $recipient,
        string $title,
        string $description,
        \DateTimeInterface $date
    ): bool {
        $startOfDay = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $endOfDay = $startOfDay->modify('+1 day');

        $count = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.title = :title')
            ->andWhere('n.description = :description')
            ->andWhere('n.createdAt >= :startOfDay')
            ->andWhere('n.createdAt < :endOfDay')
            ->setParameter('recipient', $recipient)
            ->setParameter('title', trim($title))
            ->setParameter('description', trim($description))
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}

