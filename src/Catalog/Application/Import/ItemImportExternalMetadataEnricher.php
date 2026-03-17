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

final class ItemImportExternalMetadataEnricher
{
    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    public function enrich(string $provider, array $metadata): array
    {
        if ('nukacrypt' !== strtolower(trim($provider))) {
            return $metadata;
        }

        $keywordNames = $this->extractKeywordNames($metadata['keywords'] ?? null);
        if ([] === $keywordNames) {
            return $metadata;
        }

        $metadata['keyword_names'] = $keywordNames;

        if (in_array('UnsellableObject', $keywordNames, true)) {
            $metadata['derived'] = is_array($metadata['derived'] ?? null) ? $metadata['derived'] : [];
            $metadata['derived']['tradeable'] = false;
        }

        return $metadata;
    }

    /**
     * @return list<string>
     */
    private function extractKeywordNames(mixed $keywords): array
    {
        if (!is_array($keywords) || !array_is_list($keywords)) {
            return [];
        }

        $names = [];
        foreach ($keywords as $keyword) {
            if (!is_scalar($keyword)) {
                continue;
            }

            $value = trim((string) $keyword);
            if ('' === $value) {
                continue;
            }

            $segments = preg_split('/\s*-\s*/', $value);
            if (!is_array($segments)) {
                continue;
            }

            $name = trim((string) end($segments));
            if ('' === $name) {
                continue;
            }

            $names[] = $name;
        }

        /** @var list<string> $uniqueNames */
        $uniqueNames = array_values(array_unique($names));

        return $uniqueNames;
    }
}
