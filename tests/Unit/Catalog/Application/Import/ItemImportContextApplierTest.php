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

use App\Catalog\Application\Import\ItemImportContextApplier;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;
use PHPUnit\Framework\TestCase;

final class ItemImportContextApplierTest extends TestCase
{
    private ItemImportContextApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new ItemImportContextApplier();
    }

    public function testApplyMiscRankSetsRank(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::MISC)
            ->setSourceId(1);

        $result = $this->applier->apply($item, 1, [
            'type' => ItemTypeEnum::MISC,
            'rank' => 2,
            'listNumber' => null,
            'isSpecialList' => false,
        ]);

        self::assertTrue($result['valid']);
        self::assertNull($result['warning']);
        self::assertSame(2, $item->getRank());
    }

    public function testApplyMiscRankConflictReturnsWarning(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::MISC)
            ->setSourceId(1)
            ->setRank(1);

        $result = $this->applier->apply($item, 1, [
            'type' => ItemTypeEnum::MISC,
            'rank' => 3,
            'listNumber' => null,
            'isSpecialList' => false,
        ]);

        self::assertTrue($result['valid']);
        self::assertNotNull($result['warning']);
        self::assertSame(1, $item->getRank());
    }

    public function testApplyBookAddsListAndClearsRank(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(61)
            ->setRank(4);

        $result = $this->applier->apply($item, 61, [
            'type' => ItemTypeEnum::BOOK,
            'rank' => null,
            'listNumber' => 4,
            'isSpecialList' => true,
        ]);

        self::assertTrue($result['valid']);
        self::assertNull($result['warning']);
        self::assertNull($item->getRank());
        self::assertCount(1, $item->getBookLists());
    }

    public function testApplyFailsWhenRequiredContextMissing(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::MISC)
            ->setSourceId(1);

        $miscResult = $this->applier->apply($item, 1, [
            'type' => ItemTypeEnum::MISC,
            'rank' => null,
            'listNumber' => null,
            'isSpecialList' => false,
        ]);

        self::assertFalse($miscResult['valid']);

        $bookResult = $this->applier->apply($item, 1, [
            'type' => ItemTypeEnum::BOOK,
            'rank' => null,
            'listNumber' => null,
            'isSpecialList' => false,
        ]);

        self::assertFalse($bookResult['valid']);
    }
}
