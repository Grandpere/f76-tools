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

namespace App\Identity\Application\Security;

use DateTimeImmutable;

interface ActiveUserSessionRegistry
{
    public function registerOrTouch(int $userId, string $sessionId, ?string $ipAddress, ?string $userAgent, DateTimeImmutable $now): void;

    public function hasAnySession(int $userId): bool;

    public function hasSession(int $userId, string $sessionId): bool;

    /**
     * @return list<ActiveUserSession>
     */
    public function listSessions(int $userId): array;

    public function revokeOtherSessions(int $userId, string $keepSessionId): int;

    public function revokeSession(int $userId, string $sessionId): void;
}
