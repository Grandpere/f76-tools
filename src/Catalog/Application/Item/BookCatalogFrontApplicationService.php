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

namespace App\Catalog\Application\Item;

use App\Catalog\Application\Import\ItemSourceFieldMergeDecision;
use App\Catalog\Application\Import\ItemSourceMergePolicy;
use App\Catalog\Application\Import\ItemSourceMergeResult;
use App\Catalog\Domain\Entity\ItemEntity;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookCatalogFrontApplicationService
{
    private const MERGE_PROVIDER_A = 'fandom';
    private const MERGE_PROVIDER_B = 'fallout_wiki';
    private const CANONICAL_SIGNAL_FIELDS = [
        'purchase_currency',
        'containers',
        'random_encounters',
        'enemies',
        'events',
        'expeditions',
        'daily_ops',
        'raid',
        'unused_content',
        'seasonal_content',
        'treasure_maps',
        'quests',
        'vendors',
        'world_spawns',
    ];

    public function __construct(
        private readonly BookCatalogFrontReadRepository $bookCatalogFrontReadRepository,
        private readonly ItemSourceMergePolicy $itemSourceMergePolicy,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<string> $selectedLists
     * @param list<string> $selectedKinds
     * @param list<string> $selectedVendorFilters
     * @param list<string> $selectedSignals
     *
     * @return array{
     *     rows:list<array{
     *         id:string,
     *         name:string,
     *         bookKind:string,
     *         description:?string,
     *         note:?string,
     *         unlocks:?string,
     *         price:?int,
     *         priceMinerva:?int,
     *         priceCurrencyCode:string,
     *         priceCurrencyLabel:string,
     *         vendorLabels:list<string>,
     *         vendorFlags:array{vendors:bool,vendor_minerva:bool,vendor_regs:bool,vendor_samuel:bool,vendor_mortimer:bool,vendor_giuseppe:bool},
     *         vendorInfoLabels:list<string>,
     *         isNew:bool,
     *         bookListNumbers:list<int>,
     *         canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     *     }>,
     *     totalItems:int,
     *     totalPages:int,
     *     currentPage:int,
     *     listOptions:list<int>,
     *     kindOptions:list<string>,
     *     vendorFilterOptions:list<string>,
     *     vendorInfoOptions:list<string>,
     *     signalOptions:list<string>
     * }
     */
    public function browse(?string $query, array $selectedLists, array $selectedKinds, array $selectedVendorFilters, array $selectedSignals, int $page, int $perPage): array
    {
        $items = $this->bookCatalogFrontReadRepository->findAllBooksDetailedOrdered();
        $rows = array_map($this->mapRow(...), $items);
        $listOptions = $this->extractListOptions($rows);
        $kindOptions = $this->extractKindOptions($rows);
        $vendorFilterOptions = $this->extractVendorFilterOptions($rows);
        $vendorInfoOptions = $this->extractVendorInfoOptions($rows);
        $signalOptions = $this->extractSignalOptions($rows);

        $normalizedQuery = $this->normalize($query);
        if ('' !== $normalizedQuery) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => str_contains($this->buildSearchHaystack($row), $normalizedQuery),
            ));
        }

        $selectedListNumbers = array_values(array_filter(
            array_map(
                static fn (string $value): ?int => ctype_digit($value) ? (int) $value : null,
                $selectedLists,
            ),
            static fn (?int $value): bool => null !== $value,
        ));

        if ([] !== $selectedListNumbers) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => [] !== array_intersect($selectedListNumbers, $row['bookListNumbers']),
            ));
        }

        $normalizedKinds = array_filter(
            array_map(
                fn (string $value): string => $this->normalize($value),
                $selectedKinds,
            ),
            static fn (string $value): bool => '' !== $value,
        );

        if ([] !== $normalizedKinds) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => in_array($row['bookKind'], $normalizedKinds, true),
            ));
        }

        $normalizedVendorFilters = array_filter(
            array_map(
                fn (string $value): string => $this->normalize($value),
                $selectedVendorFilters,
            ),
            static fn (string $value): bool => '' !== $value,
        );

        if ([] !== $normalizedVendorFilters) {
            $rows = array_values(array_filter(
                $rows,
                static function (array $row) use ($normalizedVendorFilters): bool {
                    foreach ($normalizedVendorFilters as $filter) {
                        if (($row['vendorFlags'][$filter] ?? false) === true) {
                            return true;
                        }
                    }

                    return false;
                },
            ));
        }

        $normalizedSignals = array_filter(
            array_map(
                fn (string $value): string => $this->normalize($value),
                $selectedSignals,
            ),
            static fn (string $value): bool => '' !== $value,
        );

        if ([] !== $normalizedSignals) {
            $rows = array_values(array_filter(
                $rows,
                static function (array $row) use ($normalizedSignals): bool {
                    $rowSignals = array_map(
                        static fn (array $signal): string => $signal['field'],
                        $row['canonicalSignals'],
                    );

                    return [] !== array_intersect($normalizedSignals, $rowSignals);
                },
            ));
        }

        $totalItems = count($rows);
        $totalPages = max(1, (int) ceil($totalItems / max(1, $perPage)));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * max(1, $perPage);

        return [
            'rows' => array_slice($rows, $offset, max(1, $perPage)),
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'listOptions' => $listOptions,
            'kindOptions' => $kindOptions,
            'vendorFilterOptions' => $vendorFilterOptions,
            'vendorInfoOptions' => $vendorInfoOptions,
            'signalOptions' => $signalOptions,
        ];
    }

    /**
     * @return array{
     *     id:string,
     *     name:string,
     *     bookKind:string,
     *     description:?string,
     *     note:?string,
     *     unlocks:?string,
     *     price:?int,
     *     priceMinerva:?int,
     *     priceCurrencyCode:string,
     *     priceCurrencyLabel:string,
     *     vendorLabels:list<string>,
     *     vendorFlags:array{vendors:bool,vendor_minerva:bool,vendor_regs:bool,vendor_samuel:bool,vendor_mortimer:bool,vendor_giuseppe:bool},
     *     vendorInfoLabels:list<string>,
     *     isNew:bool,
     *     bookListNumbers:list<int>,
     *     canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     * }
     */
    private function mapRow(ItemEntity $item): array
    {
        $mergeResult = $this->itemSourceMergePolicy->merge($item, self::MERGE_PROVIDER_A, self::MERGE_PROVIDER_B);
        $canonicalSignals = null !== $mergeResult ? $this->extractCanonicalSignals($mergeResult) : [];
        $canonicalSignals = $this->appendItemDerivedSignals($item, $canonicalSignals);
        $bookKind = $this->extractBookKind($item);
        $unlocks = $this->extractUnlocks($item, $mergeResult);
        $vendorLabels = $this->extractVendorLabels($item);
        $vendorFlags = $this->extractVendorFlags($item);
        $vendorInfoLabels = $this->extractVendorInfoLabels($item);

        $bookListNumbers = [];
        foreach ($item->getBookLists() as $bookList) {
            $bookListNumbers[] = $bookList->getListNumber();
        }
        sort($bookListNumbers);

        $priceCurrencyCode = $this->extractPurchaseCurrencyCode($mergeResult);
        $priceCurrencyLabel = $this->translator->trans('catalog_books.currency_'.$priceCurrencyCode);

        return [
            'id' => $item->getPublicId(),
            'name' => $this->translator->trans($item->getNameKey(), domain: 'items'),
            'bookKind' => $bookKind,
            'description' => null !== $item->getDescKey() ? $this->translator->trans($item->getDescKey(), domain: 'items') : null,
            'note' => null !== $item->getNoteKey() ? $this->translator->trans($item->getNoteKey(), domain: 'items') : null,
            'unlocks' => $unlocks,
            'price' => $item->getPrice(),
            'priceMinerva' => $item->getPriceMinerva(),
            'priceCurrencyCode' => $priceCurrencyCode,
            'priceCurrencyLabel' => $priceCurrencyLabel,
            'vendorLabels' => $vendorLabels,
            'vendorFlags' => $vendorFlags,
            'vendorInfoLabels' => $vendorInfoLabels,
            'isNew' => $item->isNew(),
            'bookListNumbers' => array_values(array_unique($bookListNumbers)),
            'canonicalSignals' => array_values(array_filter(
                $canonicalSignals,
                static fn (array $signal): bool => 'purchase_currency' !== $signal['field'],
            )),
        ];
    }

    /**
     * @param list<array{field:string,label:string,displayValue:string,provider:string}> $signals
     *
     * @return list<array{field:string,label:string,displayValue:string,provider:string}>
     */
    private function appendItemDerivedSignals(ItemEntity $item, array $signals): array
    {
        $existingFields = array_map(
            static fn (array $signal): string => $signal['field'],
            $signals,
        );

        if (($item->isVendorRegs() || $item->isVendorSamuel() || $item->isVendorMortimer() || $this->hasAnyVendorMetadata($item)) && !in_array('vendors', $existingFields, true)) {
            $signals[] = [
                'field' => 'vendors',
                'label' => $this->translator->trans('catalog_books.signal_vendors'),
                'displayValue' => $this->translator->trans('catalog_books.signal_enabled'),
                'provider' => 'minerva',
            ];
        }

        if ($item->isDropDailyOps() && !in_array('daily_ops', $existingFields, true)) {
            $signals[] = [
                'field' => 'daily_ops',
                'label' => $this->translator->trans('catalog_books.signal_daily_ops'),
                'displayValue' => $this->translator->trans('catalog_books.signal_enabled'),
                'provider' => 'minerva',
            ];
        }

        return $signals;
    }

    /**
     * @param array{
     *     name:string,
     *     bookKind:string,
     *     description:?string,
     *     note:?string,
     *     unlocks:?string,
     *     vendorLabels:list<string>,
     *     vendorFlags:array{vendors:bool,vendor_minerva:bool,vendor_regs:bool,vendor_samuel:bool,vendor_mortimer:bool,vendor_giuseppe:bool},
     *     vendorInfoLabels:list<string>,
     *     bookListNumbers:list<int>,
     *     canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     * } $row
     */
    private function buildSearchHaystack(array $row): string
    {
        $parts = [
            $row['name'],
            $row['bookKind'],
            (string) ($row['description'] ?? ''),
            (string) ($row['note'] ?? ''),
            (string) ($row['unlocks'] ?? ''),
            implode(' ', array_map(static fn (int $list): string => (string) $list, $row['bookListNumbers'])),
        ];

        foreach ($row['vendorLabels'] as $vendorLabel) {
            $parts[] = $vendorLabel;
        }

        foreach ($row['vendorInfoLabels'] as $vendorInfoLabel) {
            $parts[] = $vendorInfoLabel;
        }

        foreach ($row['canonicalSignals'] as $signal) {
            $parts[] = $signal['label'];
            $parts[] = $signal['displayValue'];
        }

        return $this->normalize(implode(' ', $parts));
    }

    private function extractBookKind(ItemEntity $item): string
    {
        foreach ($item->getExternalSources() as $externalSource) {
            $metadata = $externalSource->getMetadata();
            if (!is_array($metadata)) {
                continue;
            }

            $rawType = $metadata['type'] ?? null;
            if (!is_scalar($rawType)) {
                continue;
            }

            $type = strtolower(trim((string) $rawType));
            if (in_array($type, ['plan', 'recipe'], true)) {
                return $type;
            }
        }

        $name = $this->normalize($this->translator->trans($item->getNameKey(), domain: 'items'));

        if (str_starts_with($name, 'recipe:')) {
            return 'recipe';
        }

        return 'plan';
    }

    private function extractUnlocks(ItemEntity $item, ?ItemSourceMergeResult $mergeResult): ?string
    {
        if (null !== $mergeResult) {
            foreach ($mergeResult->decisions as $decision) {
                if ('unlocks' !== $decision->field) {
                    continue;
                }

                $unlocks = $this->normalizeUnlockValue($decision->value);
                if (null !== $unlocks) {
                    return $unlocks;
                }
            }
        }

        foreach ($item->getExternalSources() as $externalSource) {
            $metadata = $externalSource->getMetadata();
            if (!is_array($metadata)) {
                continue;
            }

            $unlocks = $this->normalizeUnlockValue($metadata['unlocks'] ?? null);
            if (null !== $unlocks) {
                return $unlocks;
            }
        }

        return null;
    }

    private function normalizeUnlockValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return '' !== $value ? $value : null;
        }

        if (!is_array($value)) {
            return null;
        }

        $text = $value['text'] ?? null;
        if (is_string($text)) {
            $text = trim($text);
            if ('' !== $text) {
                return $text;
            }
        }

        $icons = $value['icons'] ?? null;
        if (!is_array($icons)) {
            return null;
        }

        $labels = [];
        foreach ($icons as $icon) {
            if (!is_string($icon)) {
                continue;
            }

            $icon = trim($icon);
            if ('' === $icon) {
                continue;
            }

            $labels[] = $icon;
        }

        $labels = array_values(array_unique($labels));

        return [] !== $labels ? implode(', ', $labels) : null;
    }

    /**
     * @return list<string>
     */
    private function extractVendorLabels(ItemEntity $item): array
    {
        $labels = [];

        if ($this->hasMinervaVendor($item)) {
            $labels[] = $this->translator->trans('catalog_books.vendor_minerva');
        }

        if ($this->hasRegsVendor($item)) {
            $labels[] = $this->translator->trans('catalog_books.vendor_regs');
        }

        if ($this->hasSamuelVendor($item)) {
            $labels[] = $this->translator->trans('catalog_books.vendor_samuel');
        }

        if ($this->hasMortimerVendor($item)) {
            $labels[] = $this->translator->trans('catalog_books.vendor_mortimer');
        }

        if ($this->hasGiuseppeVendor($item)) {
            $labels[] = $this->translator->trans('catalog_books.vendor_giuseppe');
        }

        return array_values(array_unique($labels));
    }

    /**
     * @return list<string>
     */
    private function extractVendorInfoLabels(ItemEntity $item): array
    {
        $labels = [];

        if ($this->hasBullionVendorsMetadata($item)) {
            $labels[] = $this->translator->trans('catalog_books.vendor_info_bullion_vendors');
        }

        return $labels;
    }

    /**
     * @return array{vendors:bool,vendor_minerva:bool,vendor_regs:bool,vendor_samuel:bool,vendor_mortimer:bool,vendor_giuseppe:bool}
     */
    private function extractVendorFlags(ItemEntity $item): array
    {
        $vendorMinerva = $this->hasMinervaVendor($item);
        $vendorRegs = $this->hasRegsVendor($item);
        $vendorSamuel = $this->hasSamuelVendor($item);
        $vendorMortimer = $this->hasMortimerVendor($item);
        $vendorGiuseppe = $this->hasGiuseppeVendor($item);

        return [
            'vendors' => $vendorMinerva || $vendorRegs || $vendorSamuel || $vendorMortimer || $vendorGiuseppe || $this->hasBullionVendorsMetadata($item),
            'vendor_minerva' => $vendorMinerva,
            'vendor_regs' => $vendorRegs,
            'vendor_samuel' => $vendorSamuel,
            'vendor_mortimer' => $vendorMortimer,
            'vendor_giuseppe' => $vendorGiuseppe,
        ];
    }

    private function hasAnyVendorMetadata(ItemEntity $item): bool
    {
        return $this->hasMinervaVendor($item)
            || $this->hasRegsVendor($item)
            || $this->hasSamuelVendor($item)
            || $this->hasMortimerVendor($item)
            || $this->hasGiuseppeVendor($item)
            || $this->hasBullionVendorsMetadata($item);
    }

    private function hasMinervaVendor(ItemEntity $item): bool
    {
        return $this->hasVendorMetadata($item, ['minerva']);
    }

    private function hasRegsVendor(ItemEntity $item): bool
    {
        return $item->isVendorRegs() || $this->hasVendorMetadata($item, ['regs', 'reginald stone']);
    }

    private function hasSamuelVendor(ItemEntity $item): bool
    {
        return $item->isVendorSamuel() || $this->hasVendorMetadata($item, ['samuel', 'samuel (wastelanders)']);
    }

    private function hasMortimerVendor(ItemEntity $item): bool
    {
        return $item->isVendorMortimer() || $this->hasVendorMetadata($item, ['mortimer']);
    }

    private function hasGiuseppeVendor(ItemEntity $item): bool
    {
        return $this->hasVendorMetadata($item, ['giuseppe']);
    }

    private function hasBullionVendorsMetadata(ItemEntity $item): bool
    {
        return $this->hasVendorMetadata($item, ['bullion vendors']);
    }

    /**
     * @param list<string> $needles
     */
    private function hasVendorMetadata(ItemEntity $item, array $needles): bool
    {
        foreach ($item->getExternalSources() as $externalSource) {
            $metadata = $externalSource->getMetadata();
            if (!is_array($metadata)) {
                continue;
            }

            if ($this->metadataContainsVendor($metadata, $needles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<string>         $needles
     */
    private function metadataContainsVendor(array $metadata, array $needles): bool
    {
        foreach (['obtained', 'type', 'purchase_currency'] as $field) {
            $value = $metadata[$field] ?? null;
            if ($this->valueContainsAnyNeedle($value, $needles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $needles
     */
    private function valueContainsAnyNeedle(mixed $value, array $needles): bool
    {
        if (is_scalar($value)) {
            $normalized = $this->normalize((string) $value);

            foreach ($needles as $needle) {
                if (str_contains($normalized, $needle)) {
                    return true;
                }
            }

            return false;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $entry) {
            if ($this->valueContainsAnyNeedle($entry, $needles)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * @return list<array{field:string,label:string,displayValue:string,provider:string}>
     */
    private function extractCanonicalSignals(ItemSourceMergeResult $result): array
    {
        $signals = [];

        foreach ($result->decisions as $decision) {
            if (!in_array($decision->field, self::CANONICAL_SIGNAL_FIELDS, true)) {
                continue;
            }

            $displayValue = $this->normalizeSignalValue($decision);
            if (null === $displayValue) {
                continue;
            }

            $signals[] = [
                'field' => $decision->field,
                'label' => $this->translator->trans('catalog_books.signal_'.$decision->field),
                'displayValue' => $displayValue,
                'provider' => $decision->provider,
            ];
        }

        return $signals;
    }

    private function normalizeSignalValue(ItemSourceFieldMergeDecision $decision): ?string
    {
        if ('purchase_currency' === $decision->field) {
            $currency = is_string($decision->value) ? trim($decision->value) : '';
            if ('' === $currency) {
                return null;
            }

            return $this->translator->trans('catalog_books.currency_'.$currency);
        }

        if (true === $decision->value) {
            return $this->translator->trans('catalog_books.signal_enabled');
        }

        return null;
    }

    private function extractPurchaseCurrencyCode(?ItemSourceMergeResult $result): string
    {
        if (null === $result) {
            return 'caps';
        }

        foreach ($result->decisions as $decision) {
            if ('purchase_currency' !== $decision->field || !is_string($decision->value)) {
                continue;
            }

            $currency = trim($decision->value);
            if ('' !== $currency) {
                return $currency;
            }
        }

        return 'caps';
    }

    /**
     * @param list<array{bookListNumbers:list<int>}> $rows
     *
     * @return list<int>
     */
    private function extractListOptions(array $rows): array
    {
        $listOptions = [];
        foreach ($rows as $row) {
            foreach ($row['bookListNumbers'] as $listNumber) {
                $listOptions[$listNumber] = true;
            }
        }

        $numbers = array_map('intval', array_keys($listOptions));
        sort($numbers);

        return $numbers;
    }

    /**
     * @param list<array{canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>}> $rows
     *
     * @return list<string>
     */
    private function extractSignalOptions(array $rows): array
    {
        $signalOptions = [];
        foreach ($rows as $row) {
            foreach ($row['canonicalSignals'] as $signal) {
                if ('vendors' === $signal['field']) {
                    continue;
                }

                $signalOptions[$signal['field']] = true;
            }
        }

        return array_keys($signalOptions);
    }

    /**
     * @param list<array{bookKind:string}> $rows
     *
     * @return list<string>
     */
    private function extractKindOptions(array $rows): array
    {
        $kinds = [];
        foreach ($rows as $row) {
            $kinds[$row['bookKind']] = true;
        }

        return array_keys($kinds);
    }

    /**
     * @param list<array{vendorFlags:array{vendors:bool,vendor_minerva:bool,vendor_regs:bool,vendor_samuel:bool,vendor_mortimer:bool,vendor_giuseppe:bool}}> $rows
     *
     * @return list<string>
     */
    private function extractVendorFilterOptions(array $rows): array
    {
        $options = [];
        foreach ($rows as $row) {
            foreach ($row['vendorFlags'] as $key => $enabled) {
                if ($enabled) {
                    $options[$key] = true;
                }
            }
        }

        $orderedOptions = [
            'vendors',
            'vendor_minerva',
            'vendor_regs',
            'vendor_samuel',
            'vendor_mortimer',
            'vendor_giuseppe',
        ];

        return array_values(array_filter(
            $orderedOptions,
            static fn (string $option): bool => isset($options[$option]),
        ));
    }

    /**
     * @param list<array{vendorInfoLabels:list<string>}> $rows
     *
     * @return list<string>
     */
    private function extractVendorInfoOptions(array $rows): array
    {
        $options = [];
        foreach ($rows as $row) {
            foreach ($row['vendorInfoLabels'] as $label) {
                $options[$label] = true;
            }
        }

        return array_keys($options);
    }
}
