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

namespace App\Support\Application\Audit;

use App\Support\Domain\Entity\AdminAuditLogEntity;

interface AuditLogReadRepository
{
    /**
     * @return array{rows: list<AdminAuditLogEntity>, total: int}
     */
    public function findPaginated(string $query, string $action, int $page, int $perPage): array;

    /**
     * @return list<AdminAuditLogEntity>
     */
    public function findRowsPage(string $query, string $action, int $page, int $perPage): array;

    /**
     * @return list<string>
     */
    public function findDistinctActions(): array;

    /**
     * @return list<AdminAuditLogEntity>
     */
    public function findForExport(string $query, string $action, int $maxRows): array;

    /**
     * @param list<string> $actions
     */
    public function findLatestByActions(array $actions): ?AdminAuditLogEntity;
}
