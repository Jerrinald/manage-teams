<?php

namespace App\Repository;

use App\Entity\BlockedToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlockedToken>
 */
class BlockedTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedToken::class);
    }

    public function isBlocked(string $jti): bool
    {
        return (bool) $this->createQueryBuilder('b')
            ->select('1')
            ->where('b.jti = :jti')
            ->setParameter('jti', $jti)
            ->getQuery()
            ->getOneOrNullResult();
    }
}