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

final class AuditLogListApplicationService
{
    public function __construct(
        private readonly AuditLogReadRepository $auditLogRepository,
    ) {
    }

    public function list(AuditLogListQuery $query): AuditLogListResult
    {
        $result = $this->auditLogRepository->findPaginated($query->query, $query->action, $query->page, $query->perPage);
        $totalRows = $result['total'];
        $totalPages = max(1, (int) ceil($totalRows / $query->perPage));
        $page = min($query->page, $totalPages);

        if ($page !== $query->page) {
            $result = $this->auditLogRepository->findPaginated($query->query, $query->action, $page, $query->perPage);
        }

        return new AuditLogListResult(
            rows: $result['rows'],
            totalRows: $totalRows,
            query: $query->query,
            action: $query->action,
            actions: $this->auditLogRepository->findDistinctActions(),
            page: $page,
            perPage: $query->perPage,
            totalPages: $totalPages,
        );
    }
}
