<?php

namespace App\Service;

use App\Entity\Device;
use App\Entity\RefreshTokenHistory;
use App\Entity\User;
use App\Repository\DeviceRepository;
use App\Repository\RefreshTokenHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class DeviceService
{
    public const REFRESH_TOKEN_TTL = '+7 days';
    public const MAX_DEVICES_PER_USER = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeviceRepository $deviceRepository,
        private readonly RefreshTokenHistoryRepository $historyRepository,
        private readonly TokenService $tokenService,
    ) {
    }

    /**
     * @return array{device: Device, refresh_token: string}
     */
    public function createDevice(User $user, ?string $userAgent, ?string $ip): array
    {
        $now = new \DateTimeImmutable();
        $refreshToken = $this->tokenService->createRefreshToken();

        $device = (new Device())
            ->setUser($user)
            ->setUserAgent($userAgent)
            ->setIp($ip)
            ->setRefreshTokenHash($refreshToken['hash'])
            ->setExpiresAt($now->modify(self::REFRESH_TOKEN_TTL))
            ->setIsRevoked(false)
            ->setIsCompromised(false)
            ->setLastUsedAt($now)
            ->setCreatedAt($now);

        $this->entityManager->persist($device);
        $this->entityManager->flush();

        $this->removeOldDevices($user);

        return [
            'device' => $device,
            'refresh_token' => $refreshToken['plain'],
        ];
    }

    public function findDeviceByRefreshToken(string $plainToken): ?Device
    {
        return $this->deviceRepository->findByPlainRefreshToken($plainToken);
    }

    public function findOldRefreshTokenUsage(string $plainToken): ?RefreshTokenHistory
    {
        return $this->historyRepository->findByPlainRefreshToken($plainToken);
    }

    /**
     * @return array{refresh_token: string}
     */
    public function rotateRefreshToken(Device $device): array
    {
        $user = $device->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Device must have an owner.');
        }

        $oldHash = $device->getRefreshTokenHash();
        if ($oldHash !== null) {
            $history = (new RefreshTokenHistory())
                ->setUser($user)
                ->setDevice($device)
                ->setRefreshTokenHash($oldHash)
                ->setExpiresAt($device->getExpiresAt());

            $this->entityManager->persist($history);
        }

        $now = new \DateTimeImmutable();
        $refreshToken = $this->tokenService->createRefreshToken();

        $device
            ->setRefreshTokenHash($refreshToken['hash'])
            ->setExpiresAt($now->modify(self::REFRESH_TOKEN_TTL))
            ->setLastUsedAt($now);

        $this->entityManager->flush();

        return ['refresh_token' => $refreshToken['plain']];
    }

    public function revokeDevice(Device $device): void
    {
        $device
            ->setRefreshTokenHash(null)
            ->setIsRevoked(true)
            ->setLastUsedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function markCompromisedAndRevokeAll(Device $device): void
    {
        $device->setIsCompromised(true);
        $user = $device->getUser();

        if ($user !== null) {
            $this->revokeAllUserDevices($user, false);
        }

        $this->entityManager->flush();
    }

    public function revokeAllUserDevices(User $user, bool $flush = true): void
    {
        $now = new \DateTimeImmutable();

        foreach ($this->deviceRepository->findBy(['user' => $user]) as $device) {
            $device
                ->setRefreshTokenHash(null)
                ->setIsRevoked(true)
                ->setLastUsedAt($now);
        }

        $this->entityManager->createQueryBuilder()
            ->delete(RefreshTokenHistory::class, 'h')
            ->andWhere('h.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    private function removeOldDevices(User $user): void
    {
        foreach ($this->deviceRepository->findOldDevicesOverLimit($user, self::MAX_DEVICES_PER_USER) as $oldDevice) {
            $this->entityManager->remove($oldDevice);
        }

        $this->entityManager->flush();
    }
}
