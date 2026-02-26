<?php

declare(strict_types=1);

namespace App\Identity\Application\Guard;

interface IdentityRateLimiterInterface
{
    public function hitAndIsLimited(
        string $scope,
        ?string $clientIp,
        string $email,
        int $maxAttempts,
        int $windowSeconds,
    ): bool;
}
