<?php

namespace App\Repository;

use App\Entity\Notification;
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
     * @return Notification[]
     */
    public function findLatestForRole(string $role, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.target_role IS NULL OR LOWER(n.target_role) = :role')
            ->setParameter('role', mb_strtolower(trim($role)))
            ->orderBy('n.isRead', 'ASC')
            ->addOrderBy('n.dateNotification', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForRole(string $role): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.isRead = :isRead')
            ->andWhere('n.target_role IS NULL OR LOWER(n.target_role) = :role')
            ->setParameter('isRead', false)
            ->setParameter('role', mb_strtolower(trim($role)))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsUnreadForRoleAndTitle(string $role, string $title): bool
    {
        $count = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.isRead = :isRead')
            ->andWhere('LOWER(n.target_role) = :role')
            ->andWhere('n.title = :title')
            ->setParameter('isRead', false)
            ->setParameter('role', mb_strtolower(trim($role)))
            ->setParameter('title', trim($title))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function existsForRoleTitleDescriptionOnDate(string $role, string $title, string $description, \DateTimeInterface $date): bool
    {
        $count = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('LOWER(n.target_role) = :role')
            ->andWhere('n.title = :title')
            ->andWhere('n.description = :description')
            ->andWhere('n.dateNotification = :date')
            ->setParameter('role', mb_strtolower(trim($role)))
            ->setParameter('title', trim($title))
            ->setParameter('description', trim($description))
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
