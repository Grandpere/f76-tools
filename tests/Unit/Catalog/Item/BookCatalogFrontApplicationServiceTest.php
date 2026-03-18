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
    public function testBrowseFiltersByVisibleNameAndMergeStatus(): void
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

        $result = $service->browse('alpha', 'no_merge', 1, 24);

        self::assertSame(1, $result['totalItems']);
        self::assertSame('pub-alpha', $result['rows'][0]['publicId']);
        self::assertSame('no_merge', $result['rows'][0]['mergeStatus']);
        self::assertSame(1, $result['stats']['no_merge']);
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

        $result = $service->browse(null, null, 1, 24);

        self::assertCount(3, $result['rows'][0]['canonicalSignals']);
        self::assertSame('Currency', $result['rows'][0]['canonicalSignals'][0]['label']);
        self::assertSame('Caps', $result['rows'][0]['canonicalSignals'][0]['displayValue']);
        self::assertSame('Events', $result['rows'][0]['canonicalSignals'][2]['label']);
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
                    'catalog_books.signal_purchase_currency' => 'Currency',
                    'catalog_books.signal_events' => 'Events',
                    'catalog_books.signal_enabled' => 'Enabled',
                    'catalog_books.currency_caps' => 'Caps',
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
