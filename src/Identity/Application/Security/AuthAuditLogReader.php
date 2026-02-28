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

interface AuthAuditLogReader
{
    /**
     * @return list<AuthAuditLogView>
     */
    public function findLatestByUserId(int $userId, int $limit): array;

    /**
     * @return list<AuthAuditLogView>
     */
    public function findByUserIdWithFilters(int $userId, int $limit, string $levelFilter, string $query): array;
}
