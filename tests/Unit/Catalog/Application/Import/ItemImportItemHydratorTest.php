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

namespace App\Tests\Unit\Catalog\Application\Import;

use App\Catalog\Application\Import\ItemImportExternalMetadataEnricher;
use App\Catalog\Application\Import\ItemImportExternalUrlResolver;
use App\Catalog\Application\Import\ItemImportItemHydrator;
use App\Catalog\Application\Import\ItemImportValueNormalizer;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use PHPUnit\Framework\TestCase;

final class ItemImportItemHydratorTest extends TestCase
{
    public function testHydrateMapsFieldsIntoItemEntity(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(61);

        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());
        $hydrator->hydrate($item, [
            'form_id' => '0xabc',
            'editor_id' => 'ed_1',
            'price' => '250',
            'price_minerva' => '188',
            'wiki_url' => 'https://example.test',
            'tradeable' => 1,
            'new' => 1,
            'drop_raid' => 0,
            'drop_burningsprings' => 1,
            'drop_dailyops' => true,
            'vendor_regs' => 1,
            'vendor_samuel' => 0,
            'vendor_mortimer' => 1,
            'info' => 'info',
            'drop_sources' => 'drop',
            'relations' => 'relations',
        ]);

        self::assertSame(250, $item->getPrice());
        self::assertSame(188, $item->getPriceMinerva());
        self::assertTrue($item->isNew());
        self::assertFalse($item->isDropRaid());
        self::assertTrue($item->isDropBurningSprings());
        self::assertTrue($item->isDropDailyOps());
        self::assertTrue($item->isVendorRegs());
        self::assertFalse($item->isVendorSamuel());
        self::assertTrue($item->isVendorMortimer());
        self::assertSame('info', $item->getInfoHtml());
        self::assertSame('drop', $item->getDropSourcesHtml());
        self::assertSame('relations', $item->getRelationsHtml());
    }

    public function testBuildExternalSourceDataNormalizesZeroEditorIdToNull(): void
    {
        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());
        $data = $hydrator->buildExternalSourceData('nukaknights', [
            'editor_id' => '0',
        ], 62);

        self::assertArrayHasKey('editor_id', $data['metadata']);
        self::assertNull($data['metadata']['editor_id']);
    }

    public function testBuildExternalSourceDataUsesFormIdWhenAvailable(): void
    {
        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());

        $data = $hydrator->buildExternalSourceData('nukaknights', [
            'form_id' => '0052E485',
            'wiki_url' => 'https://fallout.fandom.com/wiki/Plan:_10mm_pistol',
            'custom' => 'value',
        ], 42);

        self::assertSame('0052E485', $data['externalRef']);
        self::assertSame('https://fallout.fandom.com/wiki/Plan:_10mm_pistol', $data['externalUrl']);
        self::assertSame('value', $data['metadata']['custom'] ?? null);
    }

    public function testBuildExternalSourceDataFallsBackToSourceIdRef(): void
    {
        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());

        $data = $hydrator->buildExternalSourceData('nukaknights', [
            'wiki_url' => null,
        ], 77);

        self::assertSame('source_id:77', $data['externalRef']);
        self::assertNull($data['externalUrl']);
    }

    public function testBuildExternalSourceDataBuildsNukacryptUrlFromFormId(): void
    {
        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());

        $data = $hydrator->buildExternalSourceData('nukacrypt', [
            'form_id' => '004E9FCF',
        ], 123);

        self::assertSame('004E9FCF', $data['externalRef']);
        self::assertSame('https://nukacrypt.com/FO76/w/latest/SeventySix.esm/004e9fcf', $data['externalUrl']);
    }

    public function testBuildExternalSourceDataExtractsNukacryptKeywordNamesAndTradeableHint(): void
    {
        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());

        $data = $hydrator->buildExternalSourceData('nukacrypt', [
            'form_id' => '004E9FCF',
            'keywords' => [
                'KYWD - [003e1567] - ObjectTypeRecipe',
                'KYWD - [003d4327] - UnsellableObject',
            ],
        ], 123);

        self::assertSame(['ObjectTypeRecipe', 'UnsellableObject'], $data['metadata']['keyword_names'] ?? null);
        $derived = $data['metadata']['derived'] ?? null;
        self::assertIsArray($derived);
        self::assertFalse($derived['tradeable'] ?? true);
    }

    public function testBuildExternalSourceDataDerivesCanonicalFalloutWikiAvailabilityFlags(): void
    {
        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());

        $data = $hydrator->buildExternalSourceData('fallout_wiki', [
            'obtained' => [
                'Fallout 76 Locations',
                'Quest',
                'Bottle Cap',
                'Treasure Map',
                'Fallout 76 Containers and Storage',
                'Fallout 76 Creatures',
                'Fallout 76 Seasons',
            ],
            'type' => 'Purchased With Caps',
        ], 999);

        self::assertTrue($data['metadata']['world_spawns'] ?? false);
        self::assertTrue($data['metadata']['quests'] ?? false);
        self::assertTrue($data['metadata']['vendors'] ?? false);
        self::assertTrue($data['metadata']['treasure_maps'] ?? false);
        self::assertTrue($data['metadata']['containers'] ?? false);
        self::assertTrue($data['metadata']['enemies'] ?? false);
        self::assertTrue($data['metadata']['seasonal_content'] ?? false);
        self::assertSame('caps', $data['metadata']['purchase_currency'] ?? null);
    }

    public function testBuildExternalSourceDataDerivesCanonicalFlagsFromFalloutWikiIconPayload(): void
    {
        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());

        $data = $hydrator->buildExternalSourceData('fallout_wiki', [
            'obtained' => [
                'text' => 'MODUS Science Terminal',
                'icons' => [
                    'Quest',
                ],
            ],
            'type' => [
                'text' => 'Purchased With Bullion',
                'icons' => ['Gold Bullion'],
            ],
        ], 1000);

        self::assertTrue($data['metadata']['vendors'] ?? false);
        self::assertTrue($data['metadata']['quests'] ?? false);
        self::assertSame('gold_bullion', $data['metadata']['purchase_currency'] ?? null);
    }

    public function testBuildExternalSourceDataRecognizesObservedFalloutWikiAliases(): void
    {
        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());

        $data = $hydrator->buildExternalSourceData('fallout_wiki', [
            'obtained' => [
                'Containers',
                'Enemy Drop',
                'Quests',
                'Merchants',
                'Spawned',
                'Fallout 76 Limited Time Content',
            ],
            'type' => 'Caps',
        ], 1001);

        self::assertTrue($data['metadata']['containers'] ?? false);
        self::assertTrue($data['metadata']['enemies'] ?? false);
        self::assertTrue($data['metadata']['quests'] ?? false);
        self::assertTrue($data['metadata']['vendors'] ?? false);
        self::assertTrue($data['metadata']['world_spawns'] ?? false);
        self::assertTrue($data['metadata']['seasonal_content'] ?? false);
        self::assertSame('caps', $data['metadata']['purchase_currency'] ?? null);
    }

    public function testBuildExternalSourceDataRecognizesAdditionalObservedFalloutWikiAliases(): void
    {
        $normalizer = new ItemImportValueNormalizer();
        $hydrator = new ItemImportItemHydrator($normalizer, new ItemImportExternalUrlResolver($normalizer), new ItemImportExternalMetadataEnricher());

        $data = $hydrator->buildExternalSourceData('fallout_wiki', [
            'obtained' => [
                'Fallout 76 Quests',
                'Scoreboard',
            ],
            'type' => 'bullion',
        ], 1002);

        self::assertTrue($data['metadata']['quests'] ?? false);
        self::assertTrue($data['metadata']['seasonal_content'] ?? false);
        self::assertTrue($data['metadata']['vendors'] ?? false);
        self::assertSame('gold_bullion', $data['metadata']['purchase_currency'] ?? null);
    }
}
