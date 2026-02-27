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

namespace App\Support\Application\Contact;

use App\Support\Domain\Contact\ContactMessageStatusEnum;

final readonly class ContactMessageListQuery
{
    private const DEFAULT_PER_PAGE = 30;
    private const MAX_PER_PAGE = 200;

    public function __construct(
        public string $query,
        public ?ContactMessageStatusEnum $status,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRaw(?string $rawQuery, ?string $rawStatus, ?int $rawPage, ?int $rawPerPage): self
    {
        return new self(
            query: self::sanitizeString($rawQuery),
            status: self::sanitizeStatus($rawStatus),
            page: self::sanitizePositiveInt($rawPage, 1),
            perPage: self::sanitizePositiveInt($rawPerPage, self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE),
        );
    }

    private static function sanitizeString(?string $value): string
    {
        if (null === $value) {
            return '';
        }

        return trim($value);
    }

    private static function sanitizeStatus(?string $value): ?ContactMessageStatusEnum
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);
        if ('' === $normalized) {
            return null;
        }

        return ContactMessageStatusEnum::tryFrom($normalized);
    }

    private static function sanitizePositiveInt(?int $value, int $default, ?int $max = null): int
    {
        if (!is_int($value)) {
            return $default;
        }
        $number = $value;

        if ($number < 1) {
            return $default;
        }

        if (null !== $max && $number > $max) {
            return $max;
        }

        return $number;
    }
}
