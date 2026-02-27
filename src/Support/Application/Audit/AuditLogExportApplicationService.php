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

final class AuditLogExportApplicationService
{
    private const EXPORT_MAX_ROWS = 10000;

    public function __construct(
        private readonly AuditLogReadRepositoryInterface $auditLogRepository,
    ) {
    }

    public function export(AuditLogExportQuery $query): AuditLogExportResult
    {
        return new AuditLogExportResult(
            rows: $this->auditLogRepository->findForExport($query->query, $query->action, self::EXPORT_MAX_ROWS),
            query: $query->query,
            action: $query->action,
        );
    }
}
