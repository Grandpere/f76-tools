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

    public function export(mixed $rawQuery, mixed $rawAction): AuditLogExportResult
    {
        $query = $this->sanitizeString($rawQuery);
        $action = $this->sanitizeString($rawAction);

        return new AuditLogExportResult(
            rows: $this->auditLogRepository->findForExport($query, $action, self::EXPORT_MAX_ROWS),
            query: $query,
            action: $action,
        );
    }

    private function sanitizeString(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
