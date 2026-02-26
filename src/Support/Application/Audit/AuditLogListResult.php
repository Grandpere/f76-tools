<?php

declare(strict_types=1);

namespace App\Support\Application\Audit;

use App\Entity\AdminAuditLogEntity;

final readonly class AuditLogListResult
{
    /**
     * @param list<AdminAuditLogEntity> $rows
     * @param list<string> $actions
     */
    public function __construct(
        public array $rows,
        public int $totalRows,
        public string $query,
        public string $action,
        public array $actions,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {
    }
}
