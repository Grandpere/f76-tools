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

use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Entity\ItemExternalSourceEntity;

final class ItemSourceMergePolicy
{
    private const PREFERRED_PROVIDER_FIELDS = [
        'a' => [
            'weight',
            'likely_minerva_source',
            'containers',
            'random_encounters',
            'enemies',
            'seasonal_content',
            'treasure_maps',
            'quests',
            'vendors',
            'world_spawns',
        ],
        'b' => [
            'unlocks',
            'obtained',
        ],
    ];

    private const NAME_FIELDS = ['name', 'name_en', 'name_de'];

    public function merge(ItemEntity $item, string $providerA, string $providerB): ?ItemSourceMergeResult
    {
        $sourceA = $item->findExternalSourceByProvider($providerA);
        $sourceB = $item->findExternalSourceByProvider($providerB);
        if (null === $sourceA || null === $sourceB) {
            return null;
        }

        $metadataA = $sourceA->getMetadata() ?? [];
        $metadataB = $sourceB->getMetadata() ?? [];

        $decisions = [];
        $conflicts = [];

        foreach (self::NAME_FIELDS as $field) {
            $decision = $this->mergeNameField($field, $sourceA, $sourceB, $metadataA, $metadataB);
            if ($decision instanceof ItemSourceFieldMergeDecision) {
                $decisions[] = $decision;

                continue;
            }

            if ($decision instanceof ItemSourceMergeConflict) {
                $conflicts[] = $decision;
            }
        }

        $purchaseCurrencyDecision = $this->mergePurchaseCurrencyField($sourceA, $sourceB, $metadataA, $metadataB);
        if (null !== $purchaseCurrencyDecision) {
            $decisions[] = $purchaseCurrencyDecision;
        }

        foreach (self::PREFERRED_PROVIDER_FIELDS['a'] as $field) {
            $decision = $this->mergePreferredField($field, $sourceA, $sourceB, $metadataA, $metadataB, 'a');
            if (null !== $decision) {
                $decisions[] = $decision;
            }
        }

        foreach (self::PREFERRED_PROVIDER_FIELDS['b'] as $field) {
            $decision = $this->mergePreferredField($field, $sourceA, $sourceB, $metadataA, $metadataB, 'b');
            if (null !== $decision) {
                $decisions[] = $decision;
            }
        }

        return new ItemSourceMergeResult(
            $this->resolveLabel($sourceA, $sourceB),
            $decisions,
            $conflicts,
        );
    }

    /**
     * @param array<string, mixed> $metadataA
     * @param array<string, mixed> $metadataB
     */
    private function mergeNameField(
        string $field,
        ItemExternalSourceEntity $sourceA,
        ItemExternalSourceEntity $sourceB,
        array $metadataA,
        array $metadataB,
    ): ItemSourceFieldMergeDecision|ItemSourceMergeConflict|null {
        $valueA = $metadataA[$field] ?? null;
        $valueB = $metadataB[$field] ?? null;

        $hasA = $this->hasValue($valueA);
        $hasB = $this->hasValue($valueB);

        if (!$hasA && !$hasB) {
            return null;
        }

        if ($this->normalizeLooseText($valueA) === $this->normalizeLooseText($valueB)) {
            return new ItemSourceFieldMergeDecision(
                $field,
                $sourceB->getProvider(),
                $valueB,
                'equivalent_text_prefer_provider_b',
                $valueA,
            );
        }

        $specificDecision = $this->mergeSpecificVariantName($field, $sourceA, $sourceB, $valueA, $valueB);
        if (null !== $specificDecision) {
            return $specificDecision;
        }

        if ($hasA && !$hasB) {
            return new ItemSourceFieldMergeDecision($field, $sourceA->getProvider(), $valueA, 'fallback_single_source');
        }

        if (!$hasA) {
            return new ItemSourceFieldMergeDecision($field, $sourceB->getProvider(), $valueB, 'fallback_single_source');
        }

        return new ItemSourceMergeConflict($field, $valueA, $valueB, 'name_values_diverge');
    }

    /**
     * @param array<string, mixed> $metadataA
     * @param array<string, mixed> $metadataB
     */
    private function mergePreferredField(
        string $field,
        ItemExternalSourceEntity $sourceA,
        ItemExternalSourceEntity $sourceB,
        array $metadataA,
        array $metadataB,
        string $preferredSide,
    ): ?ItemSourceFieldMergeDecision {
        $valueA = $metadataA[$field] ?? null;
        $valueB = $metadataB[$field] ?? null;

        $hasA = $this->hasValue($valueA);
        $hasB = $this->hasValue($valueB);

        if (!$hasA && !$hasB) {
            return null;
        }

        if ('a' === $preferredSide) {
            if ($hasA) {
                return new ItemSourceFieldMergeDecision(
                    $field,
                    $sourceA->getProvider(),
                    $valueA,
                    $hasB ? 'preferred_provider_a' : 'fallback_single_source',
                    $hasB ? $valueB : null,
                );
            }

            return new ItemSourceFieldMergeDecision($field, $sourceB->getProvider(), $valueB, 'fallback_provider_b');
        }

        if ($hasB) {
            return new ItemSourceFieldMergeDecision(
                $field,
                $sourceB->getProvider(),
                $valueB,
                $hasA ? 'preferred_provider_b' : 'fallback_single_source',
                $hasA ? $valueA : null,
            );
        }

        return new ItemSourceFieldMergeDecision($field, $sourceA->getProvider(), $valueA, 'fallback_provider_a');
    }

    private function resolveLabel(ItemExternalSourceEntity $sourceA, ItemExternalSourceEntity $sourceB): string
    {
        $metadataA = $sourceA->getMetadata() ?? [];
        $metadataB = $sourceB->getMetadata() ?? [];

        foreach (['name_en', 'name', 'name_de'] as $field) {
            $valueB = $metadataB[$field] ?? null;
            if (is_string($valueB) && '' !== trim($valueB)) {
                return trim($valueB);
            }

            $valueA = $metadataA[$field] ?? null;
            if (is_string($valueA) && '' !== trim($valueA)) {
                return trim($valueA);
            }
        }

        return $sourceA->getExternalRef();
    }

    private function hasValue(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }

        if (is_string($value)) {
            return '' !== trim($value);
        }

        if (is_array($value)) {
            return [] !== $value;
        }

        return true;
    }

    private function normalizeLooseText(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized);

        return trim((string) $normalized);
    }

    private function mergeSpecificVariantName(
        string $field,
        ItemExternalSourceEntity $sourceA,
        ItemExternalSourceEntity $sourceB,
        mixed $valueA,
        mixed $valueB,
    ): ?ItemSourceFieldMergeDecision {
        if (!is_string($valueA) || !is_string($valueB)) {
            return null;
        }

        $normalizedA = $this->normalizeLooseText($valueA);
        $normalizedB = $this->normalizeLooseText($valueB);

        $aHasParenthetical = str_contains($valueA, '(') && str_contains($valueA, ')');
        $bHasParenthetical = str_contains($valueB, '(') && str_contains($valueB, ')');

        if ($aHasParenthetical && !$bHasParenthetical && str_starts_with($normalizedA, $normalizedB)) {
            return new ItemSourceFieldMergeDecision(
                $field,
                $sourceA->getProvider(),
                $valueA,
                $this->sourceHasSpecificTarget($sourceB) ? 'generic_label_confirmed_by_specific_target' : 'specific_variant_preferred',
                $valueB,
            );
        }

        if ($bHasParenthetical && !$aHasParenthetical && str_starts_with($normalizedB, $normalizedA)) {
            return new ItemSourceFieldMergeDecision(
                $field,
                $sourceB->getProvider(),
                $valueB,
                $this->sourceHasSpecificTarget($sourceA) ? 'generic_label_confirmed_by_specific_target' : 'specific_variant_preferred',
                $valueA,
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadataA
     * @param array<string, mixed> $metadataB
     */
    private function mergePurchaseCurrencyField(
        ItemExternalSourceEntity $sourceA,
        ItemExternalSourceEntity $sourceB,
        array $metadataA,
        array $metadataB,
    ): ?ItemSourceFieldMergeDecision {
        $rawValueA = $metadataA['value_currency'] ?? null;
        $rawValueB = $metadataB['type'] ?? null;

        $normalizedA = $this->normalizePurchaseCurrency($rawValueA);
        $normalizedB = $this->normalizePurchaseCurrency($rawValueB);

        if (null === $normalizedA && null === $normalizedB) {
            return null;
        }

        if (null !== $normalizedA && null !== $normalizedB && $normalizedA === $normalizedB) {
            return new ItemSourceFieldMergeDecision(
                'purchase_currency',
                $sourceA->getProvider(),
                $normalizedA,
                'equivalent_purchase_currency_prefer_provider_a',
                $rawValueB,
            );
        }

        if (null !== $normalizedA) {
            return new ItemSourceFieldMergeDecision(
                'purchase_currency',
                $sourceA->getProvider(),
                $normalizedA,
                null !== $normalizedB ? 'preferred_provider_a' : 'fallback_single_source',
                $rawValueB,
            );
        }

        return new ItemSourceFieldMergeDecision(
            'purchase_currency',
            $sourceB->getProvider(),
            $normalizedB,
            'fallback_provider_b',
            $rawValueA,
        );
    }

    private function sourceHasSpecificTarget(ItemExternalSourceEntity $source): bool
    {
        $url = $source->getExternalUrl();
        if (!is_string($url) || '' === trim($url)) {
            $metadata = $source->getMetadata() ?? [];
            $wikiUrl = $metadata['wiki_url'] ?? null;
            $url = is_scalar($wikiUrl) ? (string) $wikiUrl : null;
        }

        if (!is_string($url) || '' === trim($url)) {
            return false;
        }

        $decoded = urldecode($url);

        return str_contains($decoded, '(') && str_contains($decoded, ')');
    }

    private function normalizePurchaseCurrency(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = $this->normalizeLooseText($value);
        if ('' === $normalized) {
            return null;
        }

        return match ($normalized) {
            'bottle cap', 'bottle caps', 'cap', 'caps' => 'caps',
            'gold', 'gold bullion', 'bullion' => 'gold_bullion',
            'stamp', 'stamps' => 'stamps',
            'ticket', 'tickets' => 'tickets',
            default => $normalized,
        };
    }
}
