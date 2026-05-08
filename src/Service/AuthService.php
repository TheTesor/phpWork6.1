<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public const REFRESH_OK = 'ok';
    public const REFRESH_UNAUTHORIZED = 'unauthorized';
    public const REFRESH_COMPROMISED = 'compromised';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenService $tokenService,
        private readonly DeviceService $deviceService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{access_token: string, refresh_token: string, device_id: int}|null
     */
    public function login(string $email, string $password, ?string $userAgent, ?string $ip): ?array
    {
        $user = $this->userRepository->findOneBy(['email' => mb_strtolower(trim($email))]);

        if (!$user instanceof User || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return null;
        }

        $deviceData = $this->deviceService->createDevice($user, $userAgent, $ip);

        return [
            'access_token' => $this->tokenService->createAccessToken($user),
            'refresh_token' => $deviceData['refresh_token'],
            'device_id' => $deviceData['device']->getId(),
        ];
    }

    /**
     * @return array{status: self::REFRESH_OK, access_token: string, refresh_token: string}|array{status: self::REFRESH_UNAUTHORIZED|self::REFRESH_COMPROMISED}
     */
    public function refresh(string $refreshToken): array
    {
        $device = $this->deviceService->findDeviceByRefreshToken($refreshToken);

        if ($device === null) {
            $history = $this->deviceService->findOldRefreshTokenUsage($refreshToken);

            if ($history !== null && $history->getDevice() !== null) {
                // Reuse of a rotated token means the refresh token may be stolen.
                $this->deviceService->markCompromisedAndRevokeAll($history->getDevice());

                return ['status' => self::REFRESH_COMPROMISED];
            }

            return ['status' => self::REFRESH_UNAUTHORIZED];
        }

        $now = new \DateTimeImmutable();
        if ($device->isRevoked() || $device->isCompromised() || $device->getExpiresAt() <= $now) {
            return ['status' => self::REFRESH_UNAUTHORIZED];
        }

        $user = $device->getUser();
        if (!$user instanceof User) {
            return ['status' => self::REFRESH_UNAUTHORIZED];
        }

        $rotatedToken = $this->deviceService->rotateRefreshToken($device);

        return [
            'status' => self::REFRESH_OK,
            'access_token' => $this->tokenService->createAccessToken($user),
            'refresh_token' => $rotatedToken['refresh_token'],
        ];
    }

    public function logout(string $refreshToken): void
    {
        $device = $this->deviceService->findDeviceByRefreshToken($refreshToken);

        if ($device !== null) {
            $this->deviceService->revokeDevice($device);
        }
    }

    public function onPasswordChanged(User $user): void
    {
        $this->deviceService->revokeAllUserDevices($user);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return false;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->deviceService->revokeAllUserDevices($user, false);
        $this->entityManager->flush();

        return true;
    }
}
