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
use App\Catalog\Application\Import\ItemImportFileContext;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
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

        $result = $this->applier->apply($item, 1, ItemImportFileContext::misc(2, 'nukaknights'));

        self::assertTrue($result->valid);
        self::assertNull($result->warning);
        self::assertSame(2, $item->getRank());
    }

    public function testApplyMiscRankConflictReturnsWarning(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::MISC)
            ->setSourceId(1)
            ->setRank(1);

        $result = $this->applier->apply($item, 1, ItemImportFileContext::misc(3, 'nukaknights'));

        self::assertTrue($result->valid);
        self::assertNotNull($result->warning);
        self::assertSame(1, $item->getRank());
    }

    public function testApplyBookAddsListAndClearsRank(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(61)
            ->setRank(4);

        $result = $this->applier->apply($item, 61, ItemImportFileContext::book(4, true, 'nukaknights'));

        self::assertTrue($result->valid);
        self::assertNull($result->warning);
        self::assertNull($item->getRank());
        self::assertCount(1, $item->getBookLists());
    }

    public function testApplyFailsWhenRequiredContextMissing(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::MISC)
            ->setSourceId(1);

        $invalidMiscContext = new ItemImportFileContext(
            ItemTypeEnum::MISC,
            null,
            null,
            false,
            'nukaknights',
        );
        $miscResult = $this->applier->apply($item, 1, $invalidMiscContext);

        self::assertFalse($miscResult->valid);

        $catalogBookContext = new ItemImportFileContext(
            ItemTypeEnum::BOOK,
            null,
            null,
            false,
            'fandom',
        );
        $bookResult = $this->applier->apply($item, 1, $catalogBookContext);

        self::assertTrue($bookResult->valid);
    }
}
