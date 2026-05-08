<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = $this->getJsonData($request);
        if ($data === null || !$this->hasString($data, 'email') || !$this->hasString($data, 'password')) {
            return $this->json(['message' => 'Invalid request.'], Response::HTTP_BAD_REQUEST);
        }

        $tokens = $this->authService->login(
            (string) $data['email'],
            (string) $data['password'],
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        );

        if ($tokens === null) {
            return $this->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($tokens);
    }

    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = $this->getJsonData($request);
        if ($data === null || !$this->hasString($data, 'refresh_token')) {
            return $this->json(['message' => 'Invalid request.'], Response::HTTP_BAD_REQUEST);
        }

        $tokens = $this->authService->refresh((string) $data['refresh_token']);
        if ($tokens['status'] === AuthService::REFRESH_COMPROMISED) {
            return $this->json(['message' => 'Refresh token reuse detected.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($tokens['status'] !== AuthService::REFRESH_OK) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        unset($tokens['status']);

        return $this->json($tokens);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $data = $this->getJsonData($request);
        if ($data === null || !$this->hasString($data, 'refresh_token')) {
            return $this->json(['message' => 'Invalid request.'], Response::HTTP_BAD_REQUEST);
        }

        $this->authService->logout((string) $data['refresh_token']);

        return $this->json(['success' => true]);
    }

    #[Route('/api/password/change', name: 'api_password_change', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $this->getJsonData($request);
        if ($data === null || !$this->hasString($data, 'current_password') || !$this->hasString($data, 'new_password')) {
            return $this->json(['message' => 'Invalid request.'], Response::HTTP_BAD_REQUEST);
        }

        $changed = $this->authService->changePassword(
            $user,
            (string) $data['current_password'],
            (string) $data['new_password'],
        );

        if (!$changed) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getJsonData(Request $request): ?array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasString(array $data, string $key): bool
    {
        return isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '';
    }
}
