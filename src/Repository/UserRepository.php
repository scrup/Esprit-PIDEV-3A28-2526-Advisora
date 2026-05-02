<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[]
     */
    public function findBySearchAndSort(?string $search, string $sortBy, string $direction): array
    {
        $allowedSorts = [
            'idUser' => 'u.idUser',
            'nomUser' => 'u.nomUser',
            'PrenomUser' => 'u.PrenomUser',
            'EmailUser' => 'u.EmailUser',
            'NumTelUser' => 'u.NumTelUser',
            'cin' => 'u.cin',
            'roleUser' => 'u.roleUser',
            'dateNUser' => 'u.dateNUser',
            'createdAt' => 'u.createdAt',
            'updatedAt' => 'u.updatedAt',
        ];

        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $sortField = $allowedSorts[$sortBy] ?? 'u.idUser';

        $qb = $this->createQueryBuilder('u');

        if ($search !== null && trim($search) !== '') {
            $search = trim($search);

            $qb->andWhere(
                'u.nomUser LIKE :search
                 OR u.PrenomUser LIKE :search
                 OR CONCAT(COALESCE(u.PrenomUser, :emptyNamePart), :spaceSeparator, COALESCE(u.nomUser, :emptyNamePart)) LIKE :search
                 OR CONCAT(COALESCE(u.nomUser, :emptyNamePart), :spaceSeparator, COALESCE(u.PrenomUser, :emptyNamePart)) LIKE :search
                 OR u.EmailUser LIKE :search
                 OR u.NumTelUser LIKE :search
                 OR u.cin LIKE :search
                 OR u.roleUser LIKE :search'
            )
                ->setParameter('search', '%' . $search . '%')
                ->setParameter('emptyNamePart', '')
                ->setParameter('spaceSeparator', ' ');
        }

        $qb->orderBy($sortField, $direction);

        return $qb->getQuery()->getResult();
    }

    public function findOneByEmailInsensitive(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(TRIM(u.EmailUser)) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<User>
     */
    public function findInactiveGerants(\DateTimeInterface $cutoff): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.roleUser) = :role')
            ->andWhere('u.last_activity_at IS NULL OR u.last_activity_at < :cutoff')
            ->setParameter('role', 'gerant')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('u.last_activity_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<User>
     */
    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.roleUser) = :role')
            ->setParameter('role', 'admin')
            ->orderBy('u.idUser', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<User>
     */
    public function findAdminsAndGerants(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roleUser IN (:roles)')
            ->setParameter('roles', ['admin', 'gerant'])
            ->orderBy('u.idUser', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
