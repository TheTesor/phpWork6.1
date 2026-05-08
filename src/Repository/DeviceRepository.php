<?php

namespace App\Repository;

use App\Entity\Device;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Device>
 */
class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    public function findByPlainRefreshToken(string $plainToken): ?Device
    {
        $devices = $this->createQueryBuilder('d')
            ->andWhere('d.refreshTokenHash IS NOT NULL')
            ->getQuery()
            ->getResult();

        foreach ($devices as $device) {
            if (password_verify($plainToken, $device->getRefreshTokenHash())) {
                return $device;
            }
        }

        return null;
    }

    /**
     * @return Device[]
     */
    public function findActiveByUserNewestFirst(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Device[]
     */
    public function findOldDevicesOverLimit(User $user, int $limit): array
    {
        $devices = $this->findActiveByUserNewestFirst($user);

        return array_slice($devices, $limit);
    }
}
