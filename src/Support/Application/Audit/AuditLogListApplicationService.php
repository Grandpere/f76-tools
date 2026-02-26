<?php

declare(strict_types=1);

namespace App\Support\Application\Audit;

final class AuditLogListApplicationService
{
    private const DEFAULT_PER_PAGE = 30;
    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly AuditLogReadRepositoryInterface $auditLogRepository,
    ) {
    }

    public function list(mixed $rawQuery, mixed $rawAction, mixed $rawPage, mixed $rawPerPage): AuditLogListResult
    {
        $query = $this->sanitizeString($rawQuery);
        $action = $this->sanitizeString($rawAction);
        $perPage = $this->sanitizePositiveInt($rawPerPage, self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
        $requestedPage = $this->sanitizePositiveInt($rawPage, 1);

        $result = $this->auditLogRepository->findPaginated($query, $action, $requestedPage, $perPage);
        $totalRows = $result['total'];
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page = min($requestedPage, $totalPages);

        if ($page !== $requestedPage) {
            $result = $this->auditLogRepository->findPaginated($query, $action, $page, $perPage);
        }

        return new AuditLogListResult(
            rows: $result['rows'],
            totalRows: $totalRows,
            query: $query,
            action: $action,
            actions: $this->auditLogRepository->findDistinctActions(),
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
        );
    }

    private function sanitizeString(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function sanitizePositiveInt(mixed $value, int $default, ?int $max = null): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && '' !== trim($value) && ctype_digit(trim($value))) {
            $number = (int) trim($value);
        } else {
            return $default;
        }

        if ($number < 1) {
            return $default;
        }

        if (null !== $max && $number > $max) {
            return $max;
        }

        return $number;
    }
}
