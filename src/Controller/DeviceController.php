<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DeviceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DeviceController extends AbstractController
{
    public function __construct(
        private readonly DeviceRepository $deviceRepository,
    ) {
    }

    #[Route('/api/devices', name: 'api_devices', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $devices = $this->deviceRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
        );

        return $this->json(array_map(static fn ($device): array => [
            'id' => $device->getId(),
            'ip' => $device->getIp(),
            'userAgent' => $device->getUserAgent(),
            'lastUsedAt' => $device->getLastUsedAt()->format(\DateTimeInterface::ATOM),
            'expiresAt' => $device->getExpiresAt()->format(\DateTimeInterface::ATOM),
            'isRevoked' => $device->isRevoked(),
            'isCompromised' => $device->isCompromised(),
        ], $devices));
    }
}
