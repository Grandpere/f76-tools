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
        $normalizedProvider = strtolower(trim($provider));

        if ('nukacrypt' === $normalizedProvider) {
            return $this->enrichNukacrypt($metadata);
        }

        if ('fallout_wiki' === $normalizedProvider) {
            return $this->enrichFalloutWiki($metadata);
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function enrichNukacrypt(array $metadata): array
    {
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
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function enrichFalloutWiki(array $metadata): array
    {
        $labels = $this->extractFalloutWikiLabels($metadata);
        if ([] === $labels) {
            return $metadata;
        }

        $normalizedLabels = array_map($this->normalizeLooseText(...), $labels);

        $metadata['containers'] ??= $this->labelsContainAny($normalizedLabels, [
            'fallout 76 containers and storage',
            'container spawn',
            'containers',
        ]);
        $metadata['enemies'] ??= $this->labelsContainAny($normalizedLabels, [
            'fallout 76 creatures',
            'looted from enemy',
            'enemy drop',
        ]);
        $metadata['seasonal_content'] ??= $this->labelsContainAny($normalizedLabels, [
            'fallout 76 seasons',
            'gained during season',
            'fallout 76 legacy content',
            'fallout 76 limited time content',
            'limited time item',
            'seasonal content',
            'scoreboard',
        ]);
        $metadata['treasure_maps'] ??= $this->labelsContainAny($normalizedLabels, [
            'treasure map',
        ]);
        $metadata['quests'] ??= $this->labelsContainAny($normalizedLabels, [
            'quest',
            'quests',
            'fallout 76 quests',
            'acquired via quest',
        ]);
        $metadata['vendors'] ??= $this->labelsContainAny($normalizedLabels, [
            'bottle cap',
            'caps',
            'purchased with caps',
            'gold',
            'gold bullion',
            'bullion',
            'purchased with bullion',
            'stamp',
            'stamps',
            'ticket',
            'tickets',
            'merchants',
            'fallout 76 merchants',
        ]);
        $metadata['world_spawns'] ??= $this->labelsContainAny($normalizedLabels, [
            'fallout 76 locations',
            'world spawn',
            'world spawns',
            'spawned',
        ]);

        $purchaseCurrency = $this->normalizePurchaseCurrencyFromLabels($normalizedLabels);
        if (null !== $purchaseCurrency) {
            $metadata['purchase_currency'] ??= $purchaseCurrency;
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

    /**
     * @param array<string, mixed> $metadata
     *
     * @return list<string>
     */
    private function extractFalloutWikiLabels(array $metadata): array
    {
        $labels = [];

        foreach (['obtained', 'type'] as $field) {
            $value = $metadata[$field] ?? null;
            $labels = array_merge($labels, $this->extractScalarLabels($value));
        }

        /** @var list<string> $unique */
        $unique = array_values(array_unique(array_filter(array_map('trim', $labels), static fn (string $value): bool => '' !== $value)));

        return $unique;
    }

    /**
     * @return list<string>
     */
    private function extractScalarLabels(mixed $value): array
    {
        if (is_scalar($value)) {
            return [trim((string) $value)];
        }

        if (!is_array($value)) {
            return [];
        }

        $labels = [];
        foreach ($value as $key => $entry) {
            if (is_string($key) && in_array($key, ['text', 'icons'], true)) {
                $labels = array_merge($labels, $this->extractScalarLabels($entry));

                continue;
            }

            $labels = array_merge($labels, $this->extractScalarLabels($entry));
        }

        return $labels;
    }

    /**
     * @param list<string> $labels
     * @param list<string> $needles
     */
    private function labelsContainAny(array $labels, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $labels, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeLooseText(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized);

        return trim((string) $normalized);
    }

    /**
     * @param list<string> $labels
     */
    private function normalizePurchaseCurrencyFromLabels(array $labels): ?string
    {
        foreach ($labels as $label) {
            $normalized = match ($label) {
                'bottle cap', 'bottle caps', 'cap', 'caps', 'purchased with caps' => 'caps',
                'gold', 'gold bullion', 'bullion', 'purchased with bullion' => 'gold_bullion',
                'stamp', 'stamps' => 'stamps',
                'ticket', 'tickets' => 'tickets',
                default => null,
            };

            if (null !== $normalized) {
                return $normalized;
            }
        }

        return null;
    }
}
