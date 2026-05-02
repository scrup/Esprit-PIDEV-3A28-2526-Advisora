<?php

namespace App\Repository;

use App\Entity\OtpCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OtpCode>
 */
class OtpCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OtpCode::class);
    }

    public function findLatestUnusedForPurpose(string $email, string $purpose): ?OtpCode
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.email = :email')
            ->andWhere('o.purpose = :purpose')
            ->andWhere('o.used_at IS NULL')
            ->setParameter('email', $email)
            ->setParameter('purpose', $purpose)
            ->orderBy('o.created_at', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return OtpCode[]
     */
    public function findUnusedForPurpose(string $email, string $purpose): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.email = :email')
            ->andWhere('o.purpose = :purpose')
            ->andWhere('o.used_at IS NULL')
            ->setParameter('email', $email)
            ->setParameter('purpose', $purpose)
            ->getQuery()
            ->getResult();
    }
}