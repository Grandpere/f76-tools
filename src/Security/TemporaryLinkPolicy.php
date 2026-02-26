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

namespace App\Security;

use DateInterval;
use DateTimeImmutable;

final class TemporaryLinkPolicy
{
    public function getEmailVerificationTtl(): DateInterval
    {
        return new DateInterval('P1D');
    }

    public function getResetPasswordTtl(): DateInterval
    {
        return new DateInterval('PT2H');
    }

    public function getEmailVerificationResendCooldownSeconds(): int
    {
        return 60;
    }

    public function getResetLinkCooldownSeconds(): int
    {
        return 60;
    }

    public function getResetLinkGlobalWindowSeconds(): int
    {
        return 60;
    }

    public function getResetLinkGlobalMaxRequests(): int
    {
        return 10;
    }

    public function expiresAt(DateTimeImmutable $now, DateInterval $ttl): DateTimeImmutable
    {
        return $now->add($ttl);
    }

    public function cooldownRemainingSeconds(?DateTimeImmutable $requestedAt, DateTimeImmutable $now, int $cooldownSeconds): int
    {
        if (!$requestedAt instanceof DateTimeImmutable) {
            return 0;
        }

        $elapsedSeconds = $now->getTimestamp() - $requestedAt->getTimestamp();
        if ($elapsedSeconds >= $cooldownSeconds) {
            return 0;
        }

        return $cooldownSeconds - $elapsedSeconds;
    }
}
