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

namespace App\Tests\Unit\Catalog\Item;

use App\Catalog\Application\Import\ItemSourceMergePolicy;
use App\Catalog\Application\Item\BookCatalogFrontApplicationService;
use App\Catalog\Application\Item\BookCatalogFrontReadRepository;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Progression\Application\Knowledge\PlayerKnowledgeCatalogReadRepository;
use App\Progression\Domain\Entity\PlayerEntity;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookCatalogFrontApplicationServiceTest extends TestCase
{
    public function testBrowseFiltersByVisibleNameAndList(): void
    {
        $service = $this->createService([
            $this->createBookItem(101, 'pub-alpha', 'catalog.book.alpha.name', 'Plan: Alpha Receiver', 'no_merge'),
            $this->createBookItem(102, 'pub-bravo', 'catalog.book.bravo.name', 'Recipe: Bravo Soup', 'aligned'),
        ]);

        $result = $service->browse('alpha', ['4'], [], [], [], [], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame('pub-alpha', $result['rows'][0]['id']);
        self::assertSame('Plan: Alpha Receiver', $result['rows'][0]['name']);
        self::assertSame([4], $result['rows'][0]['bookListNumbers']);
        self::assertSame([4, 7], $result['listOptions']);
    }

    public function testBrowseExposesCanonicalSignals(): void
    {
        $service = $this->createService([
            $this->createBookItem(103, 'pub-currency', 'catalog.book.currency.name', 'Plan: Currency Test', 'aligned', ['purchase_currency' => 'caps', 'events' => true]),
        ]);

        $result = $service->browse(null, [], [], [], [], [], [], ['events'], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertCount(2, $result['rows'][0]['canonicalSignals']);
        self::assertSame('Caps', $result['rows'][0]['priceCurrencyLabel']);
        self::assertSame('Containers', $result['rows'][0]['canonicalSignals'][0]['label']);
        self::assertSame('Events', $result['rows'][0]['canonicalSignals'][1]['label']);
    }

    public function testBrowseTreatsMinervaVendorsAsVendorSignal(): void
    {
        $item = $this->createBookItem(104, 'pub-samuel', 'catalog.book.samuel.name', 'Plan: Cattle prod', 'aligned');
        $item->setVendorSamuel(true);

        $service = $this->createService([$item]);

        $result = $service->browse(null, [], [], [], [], [], ['vendors'], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertContains('vendors', array_column($result['rows'][0]['canonicalSignals'], 'field'));
    }

    public function testBrowseTreatsMinervaDailyOpsFlagAsSignal(): void
    {
        $item = $this->createBookItem(105, 'pub-dailyops', 'catalog.book.dailyops.name', 'Plan: Daily Ops Test', 'aligned');
        $item->setDropDailyOps(true);

        $service = $this->createService([$item]);

        $result = $service->browse(null, [], [], [], [], [], [], ['daily_ops'], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertContains('daily_ops', array_column($result['rows'][0]['canonicalSignals'], 'field'));
    }

    public function testBrowseFiltersByBookKindAndExposesVendorLabels(): void
    {
        $plan = $this->createBookItem(106, 'pub-plan', 'catalog.book.plan.name', 'Plan: Plan Test', 'aligned', ['source_item_type' => 'plan', 'source_page' => 'Fallout_76_Weapon_Plans']);
        $plan->setVendorSamuel(true);

        $recipe = $this->createBookItem(107, 'pub-recipe', 'catalog.book.recipe.name', 'Recipe: Recipe Test', 'aligned', ['source_item_type' => 'recipe', 'source_page' => 'Fallout_76_Recipes']);

        $service = $this->createService([$plan, $recipe]);

        $result = $service->browse(null, [], ['plan'], ['weapon_plan'], [], [], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame(['plan', 'recipe'], $result['kindOptions']);
        self::assertSame(['weapon_plan', 'recipe'], $result['categoryOptions']);
        self::assertSame(['vendors', 'vendor_samuel'], $result['vendorFilterOptions']);
        self::assertSame('plan', $result['rows'][0]['bookKind']);
        self::assertSame('weapon_plan', $result['rows'][0]['bookCategory']);
        self::assertSame(['Samuel'], $result['rows'][0]['vendorLabels']);
    }

    public function testBrowseFiltersByExactVendor(): void
    {
        $samuel = $this->createBookItem(108, 'pub-vendor-samuel', 'catalog.book.vendor.samuel', 'Plan: Samuel Plan', 'aligned', ['type' => 'plan']);
        $samuel->setVendorSamuel(true);

        $regs = $this->createBookItem(109, 'pub-vendor-regs', 'catalog.book.vendor.regs', 'Plan: Regs Plan', 'aligned', ['type' => 'plan']);
        $regs->setVendorRegs(true);

        $service = $this->createService([$samuel, $regs]);

        $result = $service->browse(null, [], [], [], [], [], ['vendor_samuel'], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame(['Samuel'], $result['rows'][0]['vendorLabels']);
    }

    public function testBrowseDetectsGiuseppeFromWikiMetadata(): void
    {
        $giuseppe = $this->createBookItem(110, 'pub-vendor-giuseppe', 'catalog.book.vendor.giuseppe', 'Plan: Giuseppe Plan', 'aligned', [
            'type' => 'plan',
            'obtained' => [
                'text' => 'Tax EvasionGiuseppe',
                'icons' => ['Tax Evasion', 'Giuseppe'],
            ],
            'purchase_currency' => 'stamps',
        ]);

        $service = $this->createService([$giuseppe]);

        $result = $service->browse(null, [], [], [], [], [], ['vendor_giuseppe'], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertContains('vendor_giuseppe', $result['vendorFilterOptions']);
        self::assertSame(['Giuseppe'], $result['rows'][0]['vendorLabels']);
    }

    public function testBrowseExposesUnlocksFromSourceMetadata(): void
    {
        $item = $this->createBookItem(113, 'pub-unlocks', 'catalog.book.unlocks', 'Plan: Unlock Test', 'aligned', [
            'type' => 'plan',
            'unlocks' => ['text' => 'Atlantic City Poker Table'],
        ]);

        $service = $this->createService([$item]);

        $result = $service->browse('poker', [], [], [], [], [], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame('Atlantic City Poker Table', $result['rows'][0]['unlocks']);
    }

    public function testBrowseMatchesMixedVendorMetadataAcrossExactFilters(): void
    {
        $mixed = $this->createBookItem(111, 'pub-vendor-mixed', 'catalog.book.vendor.mixed', 'Plan: Mixed Vendor Plan', 'aligned', [
            'type' => 'plan',
            'obtained' => [
                'text' => 'Samuel or Minerva',
                'icons' => ['Samuel (Wastelanders)', 'Minerva'],
            ],
            'purchase_currency' => 'gold_bullion',
        ]);

        $service = $this->createService([$mixed]);

        $minervaResult = $service->browse(null, [], [], [], [], [], ['vendor_minerva'], [], 1, 24);
        $samuelResult = $service->browse(null, [], [], [], [], [], ['vendor_samuel'], [], 1, 24);

        self::assertSame(1, $minervaResult['totalItems']);
        self::assertSame(1, $samuelResult['totalItems']);
        self::assertContains('vendor_minerva', $minervaResult['vendorFilterOptions']);
        self::assertSame(['Minerva', 'Samuel'], $minervaResult['rows'][0]['vendorLabels']);
    }

    public function testBrowseExposesBullionVendorsAsInformationalVendorHint(): void
    {
        $item = $this->createBookItem(112, 'pub-vendor-bullion', 'catalog.book.vendor.bullion', 'Plan: Bullion Vendor Plan', 'aligned', [
            'type' => 'plan',
            'obtained' => 'Bullion vendors',
            'purchase_currency' => 'gold_bullion',
        ]);

        $service = $this->createService([$item]);

        $result = $service->browse(null, [], [], [], [], [], [], [], 1, 24);

        self::assertSame(['Bullion vendors'], $result['vendorInfoOptions']);
        self::assertTrue($result['rows'][0]['vendorFlags']['vendors']);
        self::assertSame(['Bullion vendors'], $result['rows'][0]['vendorInfoLabels']);
    }

    public function testBrowseCanFilterLearnedAndUnlearnedForPlayer(): void
    {
        $learned = $this->createBookItem(114, 'pub-learned', 'catalog.book.learned', 'Plan: Learned Plan', 'aligned');
        $unlearned = $this->createBookItem(115, 'pub-unlearned', 'catalog.book.unlearned', 'Plan: Unlearned Plan', 'aligned');
        $player = new PlayerEntity();

        $service = $this->createService([$learned, $unlearned], [$learned->getId() ?? 0]);

        $learnedResult = $service->browse(null, [], [], [], [], [], [], [], 1, 24, $player, 'learned');
        $unlearnedResult = $service->browse(null, [], [], [], [], [], [], [], 1, 24, $player, 'unlearned');

        self::assertSame(1, $learnedResult['totalItems']);
        self::assertSame('pub-learned', $learnedResult['rows'][0]['id']);
        self::assertTrue($learnedResult['rows'][0]['learned']);

        self::assertSame(1, $unlearnedResult['totalItems']);
        self::assertSame('pub-unlearned', $unlearnedResult['rows'][0]['id']);
        self::assertFalse($unlearnedResult['rows'][0]['learned']);
    }

    public function testBrowseExposesProgressSummaryForFilteredRows(): void
    {
        $learned = $this->createBookItem(116, 'pub-summary-a', 'catalog.book.summary.a', 'Plan: Summary A', 'aligned', ['type' => 'plan']);
        $alsoLearned = $this->createBookItem(117, 'pub-summary-b', 'catalog.book.summary.b', 'Plan: Summary B', 'aligned', ['type' => 'plan']);
        $unlearned = $this->createBookItem(118, 'pub-summary-c', 'catalog.book.summary.c', 'Recipe: Summary C', 'aligned', ['type' => 'recipe']);
        $player = new PlayerEntity();

        $service = $this->createService([$learned, $alsoLearned, $unlearned], [$learned->getId() ?? 0, $alsoLearned->getId() ?? 0]);
        $result = $service->browse(null, [], ['plan'], [], [], [], [], [], 1, 24, $player, 'all');

        self::assertSame(2, $result['progressSummary']['learned']);
        self::assertSame(2, $result['progressSummary']['total']);
        self::assertSame(0, $result['progressSummary']['unlearned']);
        self::assertSame(100, $result['progressSummary']['percent']);
    }

    public function testBrowseCanSortByPriceAndMinervaPrice(): void
    {
        $alpha = $this->createBookItem(119, 'pub-sort-alpha', 'catalog.book.sort.alpha', 'Plan: Sort Alpha', 'aligned', ['type' => 'plan']);
        $alpha->setPrice(450);
        $alpha->setPriceMinerva(338);

        $bravo = $this->createBookItem(120, 'pub-sort-bravo', 'catalog.book.sort.bravo', 'Plan: Sort Bravo', 'aligned', ['type' => 'plan']);
        $bravo->setPrice(250);
        $bravo->setPriceMinerva(188);

        $charlie = $this->createBookItem(121, 'pub-sort-charlie', 'catalog.book.sort.charlie', 'Plan: Sort Charlie', 'aligned', ['type' => 'plan']);
        $charlie->setPrice(1000);

        $service = $this->createService([$alpha, $bravo, $charlie]);

        $priceResult = $service->browse(null, [], [], [], [], [], [], [], 1, 24, null, 'all', 'price_asc');
        $minervaResult = $service->browse(null, [], [], [], [], [], [], [], 1, 24, null, 'all', 'price_minerva_asc');

        self::assertSame(['name_asc', 'price_asc', 'price_minerva_asc'], $priceResult['sortOptions']);
        self::assertSame(['pub-sort-bravo', 'pub-sort-alpha', 'pub-sort-charlie'], array_column($priceResult['rows'], 'id'));
        self::assertSame(['pub-sort-bravo', 'pub-sort-alpha', 'pub-sort-charlie'], array_column($minervaResult['rows'], 'id'));
    }

    public function testBrowseExposesAndFiltersBookSubcategories(): void
    {
        $weapon = $this->createBookItem(122, 'pub-weapon-subcategory', 'catalog.book.weapon.subcategory', 'Plan: Weapon Test', 'aligned', [
            'source_item_type' => 'plan',
            'source_page' => 'Fallout_76_Weapon_Plans',
            'source_section' => 'Ballistic',
        ]);
        $workshop = $this->createBookItem(123, 'pub-workshop-subcategory', 'catalog.book.workshop.subcategory', 'Plan: Workshop Test', 'aligned', [
            'source_item_type' => 'plan',
            'source_page' => 'Fallout_76_Workshop_Plans',
            'source_section' => 'Floor Decor',
        ]);

        $service = $this->createService([$weapon, $workshop]);

        $result = $service->browse(null, [], [], ['weapon_plan', 'workshop_plan'], ['ballistic'], [], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame('ballistic', $result['rows'][0]['bookSubcategory']);
        self::assertSame('Ballistic', $result['rows'][0]['bookSubcategoryLabel']);
        self::assertSame(
            [
                ['category' => 'weapon_plan', 'id' => 'ballistic', 'label' => 'Ballistic'],
                ['category' => 'workshop_plan', 'id' => 'floor_decor', 'label' => 'Floor Decor'],
            ],
            array_values(array_filter(
                $result['subcategoryOptions'],
                static fn (array $option): bool => in_array($option['id'], ['ballistic', 'floor_decor'], true),
            )),
        );
    }

    public function testBrowseFallsBackToGeneralBookDetailForGenericRecipeSections(): void
    {
        $recipe = $this->createBookItem(130, 'pub-recipe-general', 'catalog.book.recipe.general', 'Recipe: General Test', 'aligned', [
            'source_item_type' => 'recipe',
            'source_page' => 'Fallout_76_Recipes',
            'source_sections' => ['Recipes'],
        ]);

        $service = $this->createService([$recipe]);

        $result = $service->browse(null, [], [], ['recipe'], [], ['general'], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame('general', $result['rows'][0]['bookDetail']);
        self::assertSame('General', $result['rows'][0]['bookDetailLabel']);
        self::assertContains(
            ['category' => 'recipe', 'id' => 'general', 'label' => 'General'],
            $result['detailOptions'],
        );
    }

    public function testBrowseExposesAndFiltersBookDetails(): void
    {
        $recipe = $this->createBookItem(124, 'pub-recipe-detail', 'catalog.book.recipe.detail', 'Recipe: Chem Test', 'aligned', [
            'source_item_type' => 'recipe',
            'source_page' => 'Fallout_76_Recipes',
            'source_sections' => ['Recipes', 'Chems'],
        ]);
        $workshop = $this->createBookItem(125, 'pub-workshop-detail', 'catalog.book.workshop.detail', 'Plan: Bed Test', 'aligned', [
            'source_item_type' => 'plan',
            'source_page' => 'Fallout_76_Workshop_Plans',
            'source_sections' => ['Workshop plans', 'Beds'],
        ]);

        $service = $this->createService([$recipe, $workshop]);

        $result = $service->browse(null, [], [], ['recipe', 'workshop_plan'], [], ['chems'], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame('chems', $result['rows'][0]['bookDetail']);
        self::assertSame('Chems', $result['rows'][0]['bookDetailLabel']);
        self::assertSame(
            [
                ['category' => 'recipe', 'id' => 'chems', 'label' => 'Chems'],
                ['category' => 'workshop_plan', 'id' => 'beds', 'label' => 'Beds'],
            ],
            array_values(array_filter(
                $result['detailOptions'],
                static fn (array $option): bool => in_array($option['id'], ['chems', 'beds'], true),
            )),
        );
    }

    public function testBrowseExposesArmorSubcategoriesFromSourceSections(): void
    {
        $armor = $this->createBookItem(126, 'pub-armor-subcategory', 'catalog.book.armor.subcategory', 'Plan: Brotherhood Recon Armor', 'aligned', [
            'source_item_type' => 'plan',
            'source_page' => 'Fallout_76_Armor_Plans',
            'source_section' => 'Brotherhood recon armor',
        ]);
        $armorMod = $this->createBookItem(127, 'pub-armor-mod-subcategory', 'catalog.book.armor.mod.subcategory', 'Plan: Secret Service Armor Buttressed', 'aligned', [
            'source_item_type' => 'plan',
            'source_page' => 'Fallout_76_Armor_Mod_Plans',
            'source_section' => 'Secret Service armor',
        ]);

        $service = $this->createService([$armor, $armorMod]);

        $result = $service->browse(null, [], [], ['armor_plan', 'armor_mod_plan'], ['brotherhood_recon'], [], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame('armor_plan', $result['rows'][0]['bookCategory']);
        self::assertSame('brotherhood_recon', $result['rows'][0]['bookSubcategory']);
        self::assertSame('Brotherhood Recon', $result['rows'][0]['bookSubcategoryLabel']);
        self::assertSame(
            [
                ['category' => 'armor_plan', 'id' => 'brotherhood_recon', 'label' => 'Brotherhood Recon'],
                ['category' => 'armor_mod_plan', 'id' => 'secret_service', 'label' => 'Secret Service'],
            ],
            array_values(array_filter(
                $result['subcategoryOptions'],
                static fn (array $option): bool => in_array($option['id'], ['brotherhood_recon', 'secret_service'], true),
            )),
        );
    }

    public function testBrowseExposesMuniArmorWithoutStableSection(): void
    {
        $muni = $this->createBookItem(128, 'pub-armor-subcategory-muni', 'catalog.book.armor.subcategory.muni', 'Plan: Muni Armor Helmet', 'aligned', [
            'source_item_type' => 'plan',
            'source_page' => 'Fallout_76_Armor_Plans',
            'source_slug' => 'Plan:_Muni_Armor_Helmet',
        ]);

        $service = $this->createService([$muni]);

        $result = $service->browse(null, [], [], ['armor_plan'], ['muni'], [], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame('muni', $result['rows'][0]['bookSubcategory']);
        self::assertSame('Muni', $result['rows'][0]['bookSubcategoryLabel']);
    }

    /**
     * @param list<ItemEntity> $items
     * @param list<int>        $learnedItemIds
     */
    private function createService(array $items, array $learnedItemIds = []): BookCatalogFrontApplicationService
    {
        return new BookCatalogFrontApplicationService(
            new class($items) implements BookCatalogFrontReadRepository {
                /**
                 * @param list<ItemEntity> $items
                 */
                public function __construct(private readonly array $items)
                {
                }

                public function findAllBooksDetailedOrdered(): array
                {
                    return $this->items;
                }
            },
            new ItemSourceMergePolicy(),
            new class($learnedItemIds) implements PlayerKnowledgeCatalogReadRepository {
                /**
                 * @param list<int> $learnedItemIds
                 */
                public function __construct(private readonly array $learnedItemIds)
                {
                }

                public function findLearnedItemIdsByPlayer(PlayerEntity $player, ?ItemTypeEnum $type = null): array
                {
                    return $this->learnedItemIds;
                }
            },
            $this->createTranslator(),
        );
    }

    /**
     * @param array<string, mixed> $providerBExtra
     */
    private function createBookItem(int $sourceId, string $publicId, string $nameKey, string $displayName, string $mergeStatus, array $providerBExtra = []): ItemEntity
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId($sourceId)
            ->setNameKey($nameKey)
            ->setIsNew('generic_label' === $mergeStatus);

        $item->addBookList(101 === $sourceId ? 4 : 7, 101 === $sourceId);

        $this->setItemPublicId($item, $publicId);
        $this->setItemId($item, $sourceId);

        $item->upsertExternalSource('fandom', sprintf('%08X', $sourceId), 'https://example.test/fandom/'.$sourceId, [
            'name' => $displayName,
            'name_en' => $displayName,
            'containers' => true,
        ]);

        if ('no_merge' !== $mergeStatus) {
            $providerBName = match ($mergeStatus) {
                'generic_label' => preg_replace('/\s+\([^)]+\)$/', '', $displayName) ?: $displayName,
                default => $displayName,
            };

            $item->upsertExternalSource('fallout_wiki', sprintf('%08X', $sourceId), 'https://example.test/wiki/'.$sourceId, array_merge([
                'name' => $providerBName,
                'name_en' => $providerBName,
                'unlocks' => ['text' => 'Test unlock'],
            ], $providerBExtra));
        }

        return $item;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id): string {
                return match ($id) {
                    'catalog.book.alpha.name' => 'Plan: Alpha Receiver',
                    'catalog.book.bravo.name' => 'Recipe: Bravo Soup',
                    'catalog.book.currency.name' => 'Plan: Currency Test',
                    'catalog.book.samuel.name' => 'Plan: Cattle prod',
                    'catalog.book.dailyops.name' => 'Plan: Daily Ops Test',
                    'catalog.book.plan.name' => 'Plan: Plan Test',
                    'catalog.book.recipe.name' => 'Recipe: Recipe Test',
                    'catalog.book.vendor.samuel' => 'Plan: Samuel Plan',
                    'catalog.book.vendor.regs' => 'Plan: Regs Plan',
                    'catalog.book.vendor.giuseppe' => 'Plan: Giuseppe Plan',
                    'catalog.book.unlocks' => 'Plan: Unlock Test',
                    'catalog.book.vendor.mixed' => 'Plan: Mixed Vendor Plan',
                    'catalog.book.vendor.bullion' => 'Plan: Bullion Vendor Plan',
                    'catalog.book.weapon.subcategory' => 'Plan: Weapon Test',
                    'catalog.book.workshop.subcategory' => 'Plan: Workshop Test',
                    'catalog.book.recipe.detail' => 'Recipe: Chem Test',
                    'catalog.book.workshop.detail' => 'Plan: Bed Test',
                    'catalog.book.learned' => 'Plan: Learned Plan',
                    'catalog.book.unlearned' => 'Plan: Unlearned Plan',
                    'catalog.book.summary.a' => 'Plan: Summary A',
                    'catalog.book.summary.b' => 'Plan: Summary B',
                    'catalog.book.summary.c' => 'Recipe: Summary C',
                    'catalog_books.signal_purchase_currency' => 'Currency',
                    'catalog_books.signal_containers' => 'Containers',
                    'catalog_books.signal_daily_ops' => 'Daily Ops',
                    'catalog_books.signal_events' => 'Events',
                    'catalog_books.signal_vendors' => 'Vendors',
                    'catalog_books.signal_enabled' => 'Enabled',
                    'catalog_books.currency_caps' => 'Caps',
                    'catalog_books.vendor_minerva' => 'Minerva',
                    'catalog_books.vendor_samuel' => 'Samuel',
                    'catalog_books.vendor_giuseppe' => 'Giuseppe',
                    'catalog_books.unlocks' => 'Unlocks',
                    'catalog_books.vendor_info_bullion_vendors' => 'Bullion vendors',
                    default => $id,
                };
            },
        );

        return $translator;
    }

    private function setItemPublicId(ItemEntity $item, string $publicId): void
    {
        $reflection = new ReflectionProperty(ItemEntity::class, 'publicId');
        $reflection->setValue($item, $publicId);
    }

    private function setItemId(ItemEntity $item, int $id): void
    {
        $reflection = new ReflectionProperty(ItemEntity::class, 'id');
        $reflection->setValue($item, $id);
    }
}
