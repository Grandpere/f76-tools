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
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookCatalogFrontApplicationServiceTest extends TestCase
{
    public function testBrowseFiltersByVisibleNameAndList(): void
    {
        $service = new BookCatalogFrontApplicationService(
            new class([$this->createBookItem(101, 'pub-alpha', 'catalog.book.alpha.name', 'Plan: Alpha Receiver', 'no_merge'), $this->createBookItem(102, 'pub-bravo', 'catalog.book.bravo.name', 'Recipe: Bravo Soup', 'aligned')]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $result = $service->browse('alpha', ['4'], [], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame('Plan: Alpha Receiver', $result['rows'][0]['name']);
        self::assertSame([4], $result['rows'][0]['bookListNumbers']);
        self::assertSame([4, 7], $result['listOptions']);
    }

    public function testBrowseExposesCanonicalSignals(): void
    {
        $service = new BookCatalogFrontApplicationService(
            new class([$this->createBookItem(103, 'pub-currency', 'catalog.book.currency.name', 'Plan: Currency Test', 'aligned', ['purchase_currency' => 'caps', 'events' => true])]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $result = $service->browse(null, [], [], [], ['events'], 1, 24);

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

        $service = new BookCatalogFrontApplicationService(
            new class([$item]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $result = $service->browse(null, [], [], ['vendors'], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertContains('vendors', array_column($result['rows'][0]['canonicalSignals'], 'field'));
    }

    public function testBrowseTreatsMinervaDailyOpsFlagAsSignal(): void
    {
        $item = $this->createBookItem(105, 'pub-dailyops', 'catalog.book.dailyops.name', 'Plan: Daily Ops Test', 'aligned');
        $item->setDropDailyOps(true);

        $service = new BookCatalogFrontApplicationService(
            new class([$item]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $result = $service->browse(null, [], [], [], ['daily_ops'], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertContains('daily_ops', array_column($result['rows'][0]['canonicalSignals'], 'field'));
    }

    public function testBrowseFiltersByBookKindAndExposesVendorLabels(): void
    {
        $plan = $this->createBookItem(106, 'pub-plan', 'catalog.book.plan.name', 'Plan: Plan Test', 'aligned', ['type' => 'plan']);
        $plan->setVendorSamuel(true);

        $recipe = $this->createBookItem(107, 'pub-recipe', 'catalog.book.recipe.name', 'Recipe: Recipe Test', 'aligned', ['type' => 'recipe']);

        $service = new BookCatalogFrontApplicationService(
            new class([$plan, $recipe]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $result = $service->browse(null, [], ['plan'], [], [], 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame(['plan', 'recipe'], $result['kindOptions']);
        self::assertSame(['vendors', 'vendor_samuel'], $result['vendorFilterOptions']);
        self::assertSame('plan', $result['rows'][0]['bookKind']);
        self::assertSame(['Samuel'], $result['rows'][0]['vendorLabels']);
    }

    public function testBrowseFiltersByExactVendor(): void
    {
        $samuel = $this->createBookItem(108, 'pub-vendor-samuel', 'catalog.book.vendor.samuel', 'Plan: Samuel Plan', 'aligned', ['type' => 'plan']);
        $samuel->setVendorSamuel(true);

        $regs = $this->createBookItem(109, 'pub-vendor-regs', 'catalog.book.vendor.regs', 'Plan: Regs Plan', 'aligned', ['type' => 'plan']);
        $regs->setVendorRegs(true);

        $service = new BookCatalogFrontApplicationService(
            new class([$samuel, $regs]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $result = $service->browse(null, [], [], ['vendor_samuel'], [], 1, 24);

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

        $service = new BookCatalogFrontApplicationService(
            new class([$giuseppe]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $result = $service->browse(null, [], [], ['vendor_giuseppe'], [], 1, 24);

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

        $service = new BookCatalogFrontApplicationService(
            new class([$item]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $result = $service->browse('poker', [], [], [], [], 1, 24);

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

        $service = new BookCatalogFrontApplicationService(
            new class([$mixed]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $minervaResult = $service->browse(null, [], [], ['vendor_minerva'], [], 1, 24);
        $samuelResult = $service->browse(null, [], [], ['vendor_samuel'], [], 1, 24);

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

        $service = new BookCatalogFrontApplicationService(
            new class([$item]) implements BookCatalogFrontReadRepository {
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
            $this->createTranslator(),
        );

        $result = $service->browse(null, [], [], [], [], 1, 24);

        self::assertSame(['Bullion vendors'], $result['vendorInfoOptions']);
        self::assertTrue($result['rows'][0]['vendorFlags']['vendors']);
        self::assertSame(['Bullion vendors'], $result['rows'][0]['vendorInfoLabels']);
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
}
