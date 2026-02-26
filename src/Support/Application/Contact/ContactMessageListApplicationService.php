<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

use App\Domain\Support\Contact\ContactMessageStatusEnum;

final class ContactMessageListApplicationService
{
    private const DEFAULT_PER_PAGE = 30;
    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly ContactMessageReadRepositoryInterface $contactMessageRepository,
    ) {
    }

    public function list(mixed $rawQuery, mixed $rawStatus, mixed $rawPage, mixed $rawPerPage): ContactMessageListResult
    {
        $query = $this->sanitizeQuery($rawQuery);
        $status = $this->sanitizeStatus($rawStatus);
        $perPage = $this->sanitizePositiveInt($rawPerPage, self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
        $requestedPage = $this->sanitizePositiveInt($rawPage, 1);

        $result = $this->contactMessageRepository->findPaginated($query, $status, $requestedPage, $perPage);
        $totalRows = $result['total'];
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page = min($requestedPage, $totalPages);
        if ($page !== $requestedPage) {
            $result = $this->contactMessageRepository->findPaginated($query, $status, $page, $perPage);
        }

        return new ContactMessageListResult(
            rows: $result['rows'],
            totalRows: $totalRows,
            query: $query,
            status: $status,
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
        );
    }

    private function sanitizeQuery(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function sanitizeStatus(mixed $value): ?ContactMessageStatusEnum
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ('' === $normalized) {
            return null;
        }

        return ContactMessageStatusEnum::tryFrom($normalized);
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
