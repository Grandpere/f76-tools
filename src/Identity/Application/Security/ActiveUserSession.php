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

final readonly class ActiveUserSession
{
    public function __construct(
        public string $sessionId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastSeenAt,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
    }
}
