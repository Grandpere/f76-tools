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

namespace App\Progression\UI\Api;

final class PlayerKnowledgeItemPayloadSearchFilter
{
    /**
     * @param list<array<string, mixed>> $payload
     *
     * @return list<array<string, mixed>>
     */
    public function filter(array $payload, mixed $rawQuery): array
    {
        $query = $this->normalizeQuery($rawQuery);
        if (null === $query) {
            return $payload;
        }

        return array_values(array_filter(
            $payload,
            static function (array $row) use ($query): bool {
                $name = self::normalizeValue($row['name'] ?? null);
                $description = self::normalizeValue($row['description'] ?? null);
                $nameKey = self::normalizeValue($row['nameKey'] ?? null);
                $descKey = self::normalizeValue($row['descKey'] ?? null);

                return str_contains($name, $query)
                    || str_contains($description, $query)
                    || str_contains($nameKey, $query)
                    || str_contains($descKey, $query);
            },
        ));
    }

    private static function normalizeValue(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return mb_strtolower($value);
    }

    private function normalizeQuery(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $query = mb_strtolower(trim($value));

        return '' === $query ? null : $query;
    }
}
