<?php

declare(strict_types=1);

namespace App\Support\Application\Audit;

use App\Entity\AdminAuditLogEntity;

interface AuditLogReadRepositoryInterface
{
    /**
     * @return array{rows: list<AdminAuditLogEntity>, total: int}
     */
    public function findPaginated(string $query, string $action, int $page, int $perPage): array;

    /**
     * @return list<string>
     */
    public function findDistinctActions(): array;

    /**
     * @return list<AdminAuditLogEntity>
     */
    public function findForExport(string $query, string $action, int $maxRows): array;
}
