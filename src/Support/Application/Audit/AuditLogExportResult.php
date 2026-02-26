<?php

declare(strict_types=1);

namespace App\Support\Application\Audit;

use App\Entity\AdminAuditLogEntity;

final readonly class AuditLogExportResult
{
    /**
     * @param list<AdminAuditLogEntity> $rows
     */
    public function __construct(
        public array $rows,
        public string $query,
        public string $action,
    ) {
    }
}
