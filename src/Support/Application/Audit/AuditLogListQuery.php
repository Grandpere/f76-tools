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

final readonly class AuditLogListQuery
{
    private const DEFAULT_PER_PAGE = 30;
    private const MAX_PER_PAGE = 200;

    public function __construct(
        public string $query,
        public string $action,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRaw(mixed $rawQuery, mixed $rawAction, mixed $rawPage, mixed $rawPerPage): self
    {
        return new self(
            query: self::sanitizeString($rawQuery),
            action: self::sanitizeString($rawAction),
            page: self::sanitizePositiveInt($rawPage, 1),
            perPage: self::sanitizePositiveInt($rawPerPage, self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE),
        );
    }

    private static function sanitizeString(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private static function sanitizePositiveInt(mixed $value, int $default, ?int $max = null): int
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
