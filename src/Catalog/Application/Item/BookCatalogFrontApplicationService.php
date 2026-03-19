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
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Progression\Application\Knowledge\PlayerKnowledgeCatalogReadRepository;
use App\Progression\Domain\Entity\PlayerEntity;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookCatalogFrontApplicationService
{
    private const MERGE_PROVIDER_A = 'fandom';
    private const MERGE_PROVIDER_B = 'fallout_wiki';
    private const BOOK_CATEGORY_ORDER = [
        'plan',
        'weapon_plan',
        'weapon_mod_plan',
        'armor_plan',
        'apparel_plan',
        'armor_mod_plan',
        'power_armor_plan',
        'power_armor_mod_plan',
        'workshop_plan',
        'recipe',
    ];
    /**
     * @var array<string, list<string>>
     */
    private const BOOK_SUBCATEGORY_ORDER = [
        'weapon_plan' => ['ballistic', 'melee', 'thrown', 'bows', 'alien', 'camera', 'unused'],
        'weapon_mod_plan' => ['ballistic', 'melee', 'bows', 'alien', 'camera', 'unused'],
        'apparel_plan' => ['outfits', 'headwear', 'backpacks'],
        'power_armor_plan' => ['union', 'vulcan', 'hellcat', 'excavator', 'raider', 'strangler_heart', 't_45', 't_51', 't_60', 't_65', 'ultracite', 'x_01'],
        'power_armor_mod_plan' => ['union', 'vulcan', 'hellcat', 'excavator', 'raider', 'strangler_heart', 't_45', 't_51', 't_60', 't_65', 'ultracite', 'x_01', 'unused'],
        'workshop_plan' => ['floor_decor', 'wall_decor', 'lights', 'utility', 'structures', 'display', 'allies', 'crafting', 'defenses'],
    ];
    /**
     * @var array<string, string>
     */
    private const BOOK_SUBCATEGORY_LABELS = [
        'ballistic' => 'Ballistic',
        'melee' => 'Melee',
        'thrown' => 'Thrown',
        'bows' => 'Bows',
        'alien' => 'Alien',
        'camera' => 'Camera',
        'unused' => 'Unused',
        'outfits' => 'Outfits',
        'headwear' => 'Headwear',
        'backpacks' => 'Backpacks',
        'union' => 'Union',
        'vulcan' => 'Vulcan',
        'hellcat' => 'Hellcat',
        'excavator' => 'Excavator',
        'raider' => 'Raider',
        'strangler_heart' => 'Strangler Heart',
        't_45' => 'T-45',
        't_51' => 'T-51',
        't_60' => 'T-60',
        't_65' => 'T-65',
        'ultracite' => 'Ultracite',
        'x_01' => 'X-01',
        'floor_decor' => 'Floor Decor',
        'wall_decor' => 'Wall Decor',
        'lights' => 'Lights',
        'utility' => 'Utility',
        'structures' => 'Structures',
        'display' => 'Display',
        'allies' => 'Allies',
        'crafting' => 'Crafting',
        'defenses' => 'Defenses',
    ];
    /**
     * @var array<string, list<string>>
     */
    private const BOOK_DETAIL_ORDER = [
        'recipe' => ['brewing', 'chems', 'cooking_drinks', 'cooking_food', 'junk', 'serums'],
        'workshop_plan' => [
            'appliances',
            'beds',
            'chairs',
            'crafting',
            'defenses',
            'displays',
            'doors',
            'floor_decor',
            'floors',
            'food',
            'generators',
            'lights',
            'misc_structures',
            'power_connectors',
            'resources',
            'shelves',
            'stash_boxes',
            'tables',
            'turrets_traps',
            'vendors',
            'wall_decor',
            'walls',
            'water',
        ],
    ];
    /**
     * @var array<string, string>
     */
    private const BOOK_DETAIL_LABELS = [
        'brewing' => 'Brewing',
        'chems' => 'Chems',
        'cooking_drinks' => 'Cooking (Drinks)',
        'cooking_food' => 'Cooking (Food)',
        'junk' => 'Junk',
        'serums' => 'Serums',
        'appliances' => 'Appliances',
        'beds' => 'Beds',
        'chairs' => 'Chairs',
        'displays' => 'Displays',
        'doors' => 'Doors',
        'floors' => 'Floors',
        'food' => 'Food',
        'generators' => 'Generators',
        'misc_structures' => 'Misc. Structures',
        'power_connectors' => 'Power Connectors',
        'resources' => 'Resources',
        'shelves' => 'Shelves',
        'stash_boxes' => 'Stash Boxes',
        'tables' => 'Tables',
        'turrets_traps' => 'Turrets & Traps',
        'vendors' => 'Vendors',
        'walls' => 'Walls',
        'water' => 'Water',
    ];
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
        private readonly PlayerKnowledgeCatalogReadRepository $playerKnowledgeCatalogReadRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<string> $selectedLists
     * @param list<string> $selectedKinds
     * @param list<string> $selectedCategories
     * @param list<string> $selectedSubcategories
     * @param list<string> $selectedDetails
     * @param list<string> $selectedVendorFilters
     * @param list<string> $selectedSignals
     *
     * @return array{
     *     rows:list<array{
     *         id:string,
     *         name:string,
     *         bookKind:string,
     *         bookCategory:string,
     *         bookSubcategory:?string,
     *         bookSubcategoryLabel:?string,
     *         bookDetail:?string,
     *         bookDetailLabel:?string,
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
     *         learned:bool,
     *         isNew:bool,
     *         bookListNumbers:list<int>,
     *         canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     *     }>,
     *     totalItems:int,
     *     totalPages:int,
     *     currentPage:int,
     *     progressSummary:array{learned:int,total:int,unlearned:int,percent:int},
     *     listOptions:list<int>,
     *     kindOptions:list<string>,
     *     categoryOptions:list<string>,
     *     subcategoryOptions:list<array{category:string,id:string,label:string}>,
     *     detailOptions:list<array{category:string,id:string,label:string}>,
     *     sortOptions:list<string>,
     *     vendorFilterOptions:list<string>,
     *     vendorInfoOptions:list<string>,
     *     signalOptions:list<string>
     * }
     */
    public function browse(?string $query, array $selectedLists, array $selectedKinds, array $selectedCategories, array $selectedSubcategories, array $selectedDetails, array $selectedVendorFilters, array $selectedSignals, int $page, int $perPage, ?PlayerEntity $player = null, string $knowledgeFilter = 'all', string $sort = 'name_asc'): array
    {
        $items = $this->bookCatalogFrontReadRepository->findAllBooksDetailedOrdered();
        $learnedItemIds = [];
        if ($player instanceof PlayerEntity) {
            $learnedItemIds = $this->playerKnowledgeCatalogReadRepository->findLearnedItemIdsByPlayer($player, ItemTypeEnum::BOOK);
        }
        $learnedMap = array_fill_keys($learnedItemIds, true);
        $rows = array_map(
            fn (ItemEntity $item): array => $this->mapRow($item, isset($learnedMap[$item->getId() ?? 0])),
            $items,
        );
        $listOptions = $this->extractListOptions($rows);
        $kindOptions = $this->extractKindOptions($rows);
        $categoryOptions = $this->extractCategoryOptions($rows);
        $subcategoryOptions = $this->extractSubcategoryOptions($rows);
        $detailOptions = $this->extractDetailOptions($rows);
        $sortOptions = $this->extractSortOptions();
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

        $normalizedCategories = array_filter(
            array_map(
                fn (string $value): string => $this->normalize($value),
                $selectedCategories,
            ),
            static fn (string $value): bool => '' !== $value,
        );

        if ([] !== $normalizedCategories) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => in_array($row['bookCategory'], $normalizedCategories, true),
            ));
        }

        $normalizedSubcategories = array_filter(
            array_map(
                fn (string $value): string => $this->normalize($value),
                $selectedSubcategories,
            ),
            static fn (string $value): bool => '' !== $value,
        );

        if ([] !== $normalizedSubcategories) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => null !== $row['bookSubcategory'] && in_array($row['bookSubcategory'], $normalizedSubcategories, true),
            ));
        }

        $normalizedDetails = array_filter(
            array_map(
                fn (string $value): string => $this->normalize($value),
                $selectedDetails,
            ),
            static fn (string $value): bool => '' !== $value,
        );

        if ([] !== $normalizedDetails) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => null !== $row['bookDetail'] && in_array($row['bookDetail'], $normalizedDetails, true),
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

        $normalizedKnowledgeFilter = $this->normalize($knowledgeFilter);
        if ('learned' === $normalizedKnowledgeFilter) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => true === $row['learned'],
            ));
        } elseif ('unlearned' === $normalizedKnowledgeFilter) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => false === $row['learned'],
            ));
        }

        $this->sortRows($rows, $sort);

        $learnedCount = count(array_filter(
            $rows,
            static fn (array $row): bool => true === $row['learned'],
        ));
        $totalItems = count($rows);
        $totalPages = max(1, (int) ceil($totalItems / max(1, $perPage)));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * max(1, $perPage);

        /** @var list<array{
         *     id:string,
         *     name:string,
         *     bookKind:string,
         *     bookCategory:string,
         *     bookSubcategory:?string,
         *     bookSubcategoryLabel:?string,
         *     bookDetail:?string,
         *     bookDetailLabel:?string,
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
         *     learned:bool,
         *     isNew:bool,
         *     bookListNumbers:list<int>,
         *     canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
         * }> $paginatedRows
         */
        $paginatedRows = array_slice($rows, $offset, max(1, $perPage));

        return [
            'rows' => $paginatedRows,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'progressSummary' => [
                'learned' => $learnedCount,
                'total' => $totalItems,
                'unlearned' => max(0, $totalItems - $learnedCount),
                'percent' => $totalItems > 0 ? (int) round(($learnedCount / $totalItems) * 100) : 0,
            ],
            'listOptions' => $listOptions,
            'kindOptions' => $kindOptions,
            'categoryOptions' => $categoryOptions,
            'subcategoryOptions' => $subcategoryOptions,
            'detailOptions' => $detailOptions,
            'sortOptions' => $sortOptions,
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
     *     bookCategory:string,
     *     bookSubcategory:?string,
     *     bookSubcategoryLabel:?string,
     *     bookDetail:?string,
     *     bookDetailLabel:?string,
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
     *     learned:bool,
     *     isNew:bool,
     *     bookListNumbers:list<int>,
     *     canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     * }
     */
    private function mapRow(ItemEntity $item, bool $learned): array
    {
        $mergeResult = $this->itemSourceMergePolicy->merge($item, self::MERGE_PROVIDER_A, self::MERGE_PROVIDER_B);
        $canonicalSignals = null !== $mergeResult ? $this->extractCanonicalSignals($mergeResult) : [];
        $canonicalSignals = $this->appendItemDerivedSignals($item, $canonicalSignals);
        $bookKind = $this->extractBookKind($item);
        $bookCategory = $this->extractBookCategory($item, $bookKind);
        $bookSubcategory = $this->extractBookSubcategory($item, $bookCategory);
        $bookDetail = $this->extractBookDetail($item, $bookCategory);
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
            'bookCategory' => $bookCategory,
            'bookSubcategory' => $bookSubcategory,
            'bookSubcategoryLabel' => null !== $bookSubcategory ? (self::BOOK_SUBCATEGORY_LABELS[$bookSubcategory] ?? $bookSubcategory) : null,
            'bookDetail' => $bookDetail,
            'bookDetailLabel' => null !== $bookDetail ? (self::BOOK_DETAIL_LABELS[$bookDetail] ?? $bookDetail) : null,
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
            'learned' => $learned,
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
     *     bookCategory:string,
     *     bookSubcategory:?string,
     *     bookSubcategoryLabel:?string,
     *     bookDetail:?string,
     *     bookDetailLabel:?string,
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
            $this->translator->trans('catalog_books.category_'.$row['bookCategory']),
            (string) ($row['bookSubcategoryLabel'] ?? ''),
            (string) ($row['bookDetailLabel'] ?? ''),
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

            foreach (['type', 'source_item_type'] as $typeKey) {
                $rawType = $metadata[$typeKey] ?? null;
                if (!is_scalar($rawType)) {
                    continue;
                }

                $type = strtolower(trim((string) $rawType));
                if (in_array($type, ['plan', 'recipe'], true)) {
                    return $type;
                }
            }
        }

        $name = $this->normalize($this->translator->trans($item->getNameKey(), domain: 'items'));

        if (str_starts_with($name, 'recipe:')) {
            return 'recipe';
        }

        return 'plan';
    }

    private function extractBookCategory(ItemEntity $item, string $bookKind): string
    {
        if ('recipe' === $bookKind) {
            return 'recipe';
        }

        foreach ($item->getExternalSources() as $externalSource) {
            $metadata = $externalSource->getMetadata();
            if (!is_array($metadata)) {
                continue;
            }

            $sourcePage = $this->normalize($this->stringFromMetadata($metadata, 'source_page'));
            $sourcePageUrl = $this->normalize($this->stringFromMetadata($metadata, 'source_page_url'));
            $haystack = trim($sourcePage.' '.$sourcePageUrl);

            if (str_contains($haystack, 'power_armor_mod')) {
                return 'power_armor_mod_plan';
            }
            if (str_contains($haystack, 'power_armor')) {
                return 'power_armor_plan';
            }
            if (str_contains($haystack, 'weapon_mod')) {
                return 'weapon_mod_plan';
            }
            if (str_contains($haystack, 'weapon')) {
                return 'weapon_plan';
            }
            if (str_contains($haystack, 'apparel')) {
                return 'apparel_plan';
            }
            if (str_contains($haystack, 'armor_mod')) {
                return 'armor_mod_plan';
            }
            if (str_contains($haystack, 'armor')) {
                return 'armor_plan';
            }
            if (str_contains($haystack, 'workshop')) {
                return 'workshop_plan';
            }
        }

        return 'plan';
    }

    private function extractBookSubcategory(ItemEntity $item, string $bookCategory): ?string
    {
        if (!array_key_exists($bookCategory, self::BOOK_SUBCATEGORY_ORDER)) {
            return null;
        }

        foreach ($this->sortSourcesForTaxonomy($item) as $metadata) {
            $section = $this->normalize($this->stringFromMetadata($metadata, 'source_section'));
            if ('' === $section) {
                continue;
            }

            if (in_array($bookCategory, ['weapon_plan', 'weapon_mod_plan'], true)) {
                $subcategory = $this->matchWeaponSubcategory($section);
            } elseif ('apparel_plan' === $bookCategory) {
                $subcategory = $this->matchApparelSubcategory($section);
            } elseif (in_array($bookCategory, ['power_armor_plan', 'power_armor_mod_plan'], true)) {
                $subcategory = $this->matchPowerArmorSubcategory($section);
            } else {
                $subcategory = $this->matchWorkshopSubcategory($section);
            }

            if (null !== $subcategory) {
                return $subcategory;
            }
        }

        return null;
    }

    private function extractBookDetail(ItemEntity $item, string $bookCategory): ?string
    {
        if (!array_key_exists($bookCategory, self::BOOK_DETAIL_ORDER)) {
            return null;
        }

        foreach ($this->sortSourcesForTaxonomy($item) as $metadata) {
            $sourceSections = $metadata['source_sections'] ?? null;
            if (!is_array($sourceSections)) {
                continue;
            }

            $normalizedSections = array_map(
                fn (mixed $value): string => $this->normalize(is_scalar($value) ? (string) $value : ''),
                $sourceSections,
            );
            $normalizedSections = array_values($normalizedSections);

            if ('recipe' === $bookCategory) {
                $detail = $this->matchRecipeDetail($normalizedSections);
            } else {
                $detail = $this->matchWorkshopDetail($normalizedSections);
            }

            if (null !== $detail) {
                return $detail;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function stringFromMetadata(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? null;
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return '' !== $normalized ? $normalized : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sortSourcesForTaxonomy(ItemEntity $item): array
    {
        $sources = [];
        foreach ($item->getExternalSources() as $externalSource) {
            $metadata = $externalSource->getMetadata();
            if (!is_array($metadata)) {
                continue;
            }

            $sources[] = [
                'provider' => $externalSource->getProvider(),
                'metadata' => $metadata,
            ];
        }

        usort($sources, static function (array $left, array $right): int {
            $leftWeight = match ($left['provider']) {
                'fallout_wiki' => 0,
                'fandom' => 1,
                default => 2,
            };
            $rightWeight = match ($right['provider']) {
                'fallout_wiki' => 0,
                'fandom' => 1,
                default => 2,
            };

            return $leftWeight <=> $rightWeight;
        });

        return array_map(
            static fn (array $source): array => $source['metadata'],
            $sources,
        );
    }

    private function matchWeaponSubcategory(string $section): ?string
    {
        foreach (self::BOOK_SUBCATEGORY_ORDER['weapon_plan'] as $subcategory) {
            if (str_contains($section, str_replace('_', ' ', $subcategory))) {
                return $subcategory;
            }
        }

        return null;
    }

    private function matchApparelSubcategory(string $section): ?string
    {
        return match (true) {
            str_contains($section, 'headwear') => 'headwear',
            str_contains($section, 'backpacks') => 'backpacks',
            str_contains($section, 'outfits') => 'outfits',
            default => null,
        };
    }

    private function matchPowerArmorSubcategory(string $section): ?string
    {
        $normalized = str_replace(['<span></span>', 'power armor'], '', $section);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);

        return match (true) {
            str_contains($normalized, 'strangler heart') => 'strangler_heart',
            str_contains($normalized, 'excavator') => 'excavator',
            str_contains($normalized, 'raider') => 'raider',
            str_contains($normalized, 't-45') => 't_45',
            str_contains($normalized, 't-51') => 't_51',
            str_contains($normalized, 't-60') => 't_60',
            str_contains($normalized, 't-65') => 't_65',
            str_contains($normalized, 'ultracite') => 'ultracite',
            str_contains($normalized, 'union') => 'union',
            str_contains($normalized, 'hellcat') => 'hellcat',
            str_contains($normalized, 'x-01') => 'x_01',
            str_contains($normalized, 'vulcan') => 'vulcan',
            str_contains($normalized, 'unused') => 'unused',
            default => null,
        };
    }

    private function matchWorkshopSubcategory(string $section): ?string
    {
        return match (true) {
            str_contains($section, 'floor decor') => 'floor_decor',
            str_contains($section, 'wall decor') => 'wall_decor',
            str_contains($section, 'lights') => 'lights',
            str_contains($section, 'utility') => 'utility',
            str_contains($section, 'structures') => 'structures',
            str_contains($section, 'display') => 'display',
            str_contains($section, 'allies') => 'allies',
            str_contains($section, 'crafting') => 'crafting',
            str_contains($section, 'defenses') => 'defenses',
            default => null,
        };
    }

    /**
     * @param list<string> $sections
     */
    private function matchRecipeDetail(array $sections): ?string
    {
        $haystack = implode(' ', $sections);

        return match (true) {
            str_contains($haystack, 'brewing') => 'brewing',
            str_contains($haystack, 'chems') => 'chems',
            str_contains($haystack, 'cooking (drinks)') => 'cooking_drinks',
            str_contains($haystack, 'cooking (food)') => 'cooking_food',
            str_contains($haystack, 'junk') => 'junk',
            str_contains($haystack, 'serums') => 'serums',
            default => null,
        };
    }

    /**
     * @param list<string> $sections
     */
    private function matchWorkshopDetail(array $sections): ?string
    {
        $haystack = implode(' ', $sections);

        return match (true) {
            str_contains($haystack, 'appliances') => 'appliances',
            str_contains($haystack, 'beds') => 'beds',
            str_contains($haystack, 'chairs') => 'chairs',
            str_contains($haystack, 'crafting') => 'crafting',
            str_contains($haystack, 'defenses') => 'defenses',
            str_contains($haystack, 'displays') => 'displays',
            str_contains($haystack, 'doors') => 'doors',
            str_contains($haystack, 'floor decor') => 'floor_decor',
            str_contains($haystack, 'floors') => 'floors',
            str_contains($haystack, 'food') => 'food',
            str_contains($haystack, 'generators') => 'generators',
            str_contains($haystack, 'lights') => 'lights',
            str_contains($haystack, 'misc. structures') => 'misc_structures',
            str_contains($haystack, 'power connectors') => 'power_connectors',
            str_contains($haystack, 'resources') => 'resources',
            str_contains($haystack, 'shelves') => 'shelves',
            str_contains($haystack, 'stash boxes') => 'stash_boxes',
            str_contains($haystack, 'tables') => 'tables',
            str_contains($haystack, 'turrets &amp; traps'), str_contains($haystack, 'turrets & traps') => 'turrets_traps',
            str_contains($haystack, 'vendors') => 'vendors',
            str_contains($haystack, 'wall decor') => 'wall_decor',
            str_contains($haystack, 'walls') => 'walls',
            str_contains($haystack, 'water') => 'water',
            default => null,
        };
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
     * @param list<array{
     *     id:string,
     *     name:string,
     *     bookKind:string,
     *     bookCategory:string,
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
     *     learned:bool,
     *     isNew:bool,
     *     bookListNumbers:list<int>,
     *     canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     * }>$rows
     */
    private function sortRows(array &$rows, string $sort): void
    {
        $normalizedSort = $this->normalize($sort);
        if (!in_array($normalizedSort, $this->extractSortOptions(), true)) {
            $normalizedSort = 'name_asc';
        }

        usort($rows, function (array $left, array $right) use ($normalizedSort): int {
            return match ($normalizedSort) {
                'price_asc' => $this->compareNullableIntThenName($left['price'], $right['price'], $left['name'], $right['name']),
                'price_minerva_asc' => $this->compareNullableIntThenName($left['priceMinerva'], $right['priceMinerva'], $left['name'], $right['name']),
                default => $this->compareNames($left['name'], $right['name']),
            };
        });
    }

    private function compareNames(string $left, string $right): int
    {
        return strnatcasecmp($left, $right);
    }

    private function compareNullableIntThenName(?int $leftValue, ?int $rightValue, string $leftName, string $rightName): int
    {
        if (null === $leftValue && null === $rightValue) {
            return $this->compareNames($leftName, $rightName);
        }

        if (null === $leftValue) {
            return 1;
        }

        if (null === $rightValue) {
            return -1;
        }

        if ($leftValue !== $rightValue) {
            return $leftValue <=> $rightValue;
        }

        return $this->compareNames($leftName, $rightName);
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
     * @param list<array{bookCategory:string}> $rows
     *
     * @return list<string>
     */
    private function extractCategoryOptions(array $rows): array
    {
        $presentCategories = [];
        foreach ($rows as $row) {
            $presentCategories[$row['bookCategory']] = true;
        }

        return array_values(array_filter(
            self::BOOK_CATEGORY_ORDER,
            static fn (string $category): bool => isset($presentCategories[$category]),
        ));
    }

    /**
     * @param list<array{bookCategory:string,bookSubcategory:?string,bookSubcategoryLabel:?string}> $rows
     *
     * @return list<array{category:string,id:string,label:string}>
     */
    private function extractSubcategoryOptions(array $rows): array
    {
        $present = [];
        foreach ($rows as $row) {
            if (null === $row['bookSubcategory'] || null === $row['bookSubcategoryLabel']) {
                continue;
            }

            $key = $row['bookCategory'].':'.$row['bookSubcategory'];
            $present[$key] = [
                'category' => $row['bookCategory'],
                'id' => $row['bookSubcategory'],
                'label' => $row['bookSubcategoryLabel'],
            ];
        }

        $options = array_values($present);
        usort($options, function (array $left, array $right): int {
            $leftCategoryIndex = array_search($left['category'], self::BOOK_CATEGORY_ORDER, true);
            $rightCategoryIndex = array_search($right['category'], self::BOOK_CATEGORY_ORDER, true);
            $leftCategoryIndex = false === $leftCategoryIndex ? PHP_INT_MAX : $leftCategoryIndex;
            $rightCategoryIndex = false === $rightCategoryIndex ? PHP_INT_MAX : $rightCategoryIndex;
            if ($leftCategoryIndex !== $rightCategoryIndex) {
                return $leftCategoryIndex <=> $rightCategoryIndex;
            }

            $orderedSubcategories = self::BOOK_SUBCATEGORY_ORDER[$left['category']] ?? [];
            $leftSubcategoryIndex = array_search($left['id'], $orderedSubcategories, true);
            $rightSubcategoryIndex = array_search($right['id'], $orderedSubcategories, true);
            $leftSubcategoryIndex = false === $leftSubcategoryIndex ? PHP_INT_MAX : $leftSubcategoryIndex;
            $rightSubcategoryIndex = false === $rightSubcategoryIndex ? PHP_INT_MAX : $rightSubcategoryIndex;
            if ($leftSubcategoryIndex !== $rightSubcategoryIndex) {
                return $leftSubcategoryIndex <=> $rightSubcategoryIndex;
            }

            return strnatcasecmp($left['label'], $right['label']);
        });

        return $options;
    }

    /**
     * @param list<array{
     *     bookCategory:string,
     *     bookDetail:?string,
     *     bookDetailLabel:?string
     * }> $rows
     *
     * @return list<array{category:string,id:string,label:string}>
     */
    private function extractDetailOptions(array $rows): array
    {
        $seen = [];
        $options = [];

        foreach (self::BOOK_DETAIL_ORDER as $category => $details) {
            foreach ($details as $detail) {
                foreach ($rows as $row) {
                    if ($row['bookCategory'] !== $category || ($row['bookDetail'] ?? null) !== $detail) {
                        continue;
                    }

                    $label = $row['bookDetailLabel'] ?? self::BOOK_DETAIL_LABELS[$detail] ?? $detail;
                    $key = $category.'|'.$detail;
                    if (isset($seen[$key])) {
                        continue 2;
                    }

                    $seen[$key] = true;
                    $options[] = [
                        'category' => $category,
                        'id' => $detail,
                        'label' => (string) $label,
                    ];

                    continue 2;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function extractSortOptions(): array
    {
        return [
            'name_asc',
            'price_asc',
            'price_minerva_asc',
        ];
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
