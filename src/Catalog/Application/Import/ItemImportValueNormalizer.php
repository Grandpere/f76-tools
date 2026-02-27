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

namespace App\Catalog\Application\Import;

use Stringable;

final class ItemImportValueNormalizer
{
    public function toNullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value) && !is_numeric($value) && !$value instanceof Stringable) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    public function toNullableInt(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    public function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return 1 === (int) $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return '1' === $normalized || 'true' === $normalized || 'yes' === $normalized;
        }

        return false;
    }

    /**
     * @param array<mixed> $row
     * @param list<string> $keys
     */
    public function toBoolFromRowAny(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            if ($this->toBool($row[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $row
     *
     * @return array<string, mixed>
     */
    public function normalizePayload(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
