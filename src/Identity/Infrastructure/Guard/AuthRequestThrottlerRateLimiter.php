<?php

declare(strict_types=1);

/*
 * This file is part of a F76 project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Identity\Infrastructure\Guard;

use App\Identity\Application\Guard\IdentityRateLimiterInterface;

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
