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

final readonly class AuditLogExportQuery
{
    public function __construct(
        public string $query,
        public string $action,
    ) {
    }

    public static function fromRaw(?string $rawQuery, ?string $rawAction): self
    {
        return new self(
            query: self::sanitizeString($rawQuery),
            action: self::sanitizeString($rawAction),
        );
    }

    private static function sanitizeString(?string $value): string
    {
        if (null === $value) {
            return '';
        }

        return trim($value);
    }
}
