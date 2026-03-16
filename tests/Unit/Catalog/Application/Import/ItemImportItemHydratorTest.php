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

        $hydrator = new ItemImportItemHydrator(new ItemImportValueNormalizer());
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

        self::assertSame('0xabc', $item->getFormId());
        self::assertSame('ed_1', $item->getEditorId());
        self::assertSame(250, $item->getPrice());
        self::assertSame(188, $item->getPriceMinerva());
        self::assertSame('https://example.test', $item->getWikiUrl());
        self::assertTrue($item->isTradeable());
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
        self::assertIsArray($item->getPayload());
    }

    public function testHydrateNormalizesZeroEditorIdToNull(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(62);

        $hydrator = new ItemImportItemHydrator(new ItemImportValueNormalizer());
        $hydrator->hydrate($item, [
            'editor_id' => '0',
        ]);

        self::assertNull($item->getEditorId());
    }
}
