<?php

namespace App\Repository;

use App\Entity\RefreshTokenHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshTokenHistory>
 */
class RefreshTokenHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshTokenHistory::class);
    }

    public function findByPlainRefreshToken(string $plainToken): ?RefreshTokenHistory
    {
        $items = $this->createQueryBuilder('h')
            ->getQuery()
            ->getResult();

        foreach ($items as $item) {
            if (password_verify($plainToken, $item->getRefreshTokenHash())) {
                return $item;
            }
        }

        return null;
    }
}
