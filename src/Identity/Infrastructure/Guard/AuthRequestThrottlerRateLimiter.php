<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Guard;

use App\Identity\Application\Guard\IdentityRateLimiterInterface;
use App\Service\AuthRequestThrottler;

final class AuthRequestThrottlerRateLimiter implements IdentityRateLimiterInterface
{
    public function __construct(
        private readonly AuthRequestThrottler $authRequestThrottler,
    ) {
    }

    public function hitAndIsLimited(
        string $scope,
        ?string $clientIp,
        string $email,
        int $maxAttempts,
        int $windowSeconds,
    ): bool {
        return $this->authRequestThrottler->hitAndIsLimited(
            scope: $scope,
            clientIp: $clientIp,
            email: $email,
            maxAttempts: $maxAttempts,
            windowSeconds: $windowSeconds,
        );
    }
}
