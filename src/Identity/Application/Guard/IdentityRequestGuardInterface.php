<?php

declare(strict_types=1);

namespace App\Identity\Application\Guard;

interface IdentityRequestGuardInterface
{
    public function guard(
        string $scope,
        string $csrfTokenId,
        string $csrfToken,
        string $honeypotValue,
        string $captchaResponse,
        ?string $clientIp,
        string $email,
        int $maxAttempts,
        int $windowSeconds,
    ): IdentityRequestGuardResult;
}
