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

interface AuthAuditLogWriter
{
    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public function write(string $level, string $event, ?string $email, ?string $clientIp, array $context): void;
}
