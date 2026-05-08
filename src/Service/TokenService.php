<?php

namespace App\Service;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class TokenService
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function createAccessToken(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    /**
     * Plain refresh token is shown to the client once; only the hash is stored.
     *
     * @return array{plain: string, hash: string}
     */
    public function createRefreshToken(): array
    {
        $plainToken = bin2hex(random_bytes(64));

        return [
            'plain' => $plainToken,
            'hash' => password_hash($plainToken, PASSWORD_BCRYPT),
        ];
    }
}
