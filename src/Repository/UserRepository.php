<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findBySearchAndSort(?string $search, string $sortBy, string $direction): array
    {
        $qb = $this->createQueryBuilder('u');

        if ($search !== null && trim($search) !== '') {
            $search = trim($search);

            $qb->andWhere(
                'u.nomUser LIKE :search
                 OR u.PrenomUser LIKE :search
                 OR CONCAT(u.PrenomUser, \' \', u.nomUser) LIKE :search
                 OR CONCAT(u.nomUser, \' \', u.PrenomUser) LIKE :search
                 OR u.EmailUser LIKE :search
                 OR u.NumTelUser LIKE :search
                 OR u.cin LIKE :search
                 OR u.roleUser LIKE :search'
            )
            ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('u.' . $sortBy, $direction);

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
}