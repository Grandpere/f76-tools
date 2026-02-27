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

use App\Catalog\Application\Import\ItemImportTranslationCatalogBuilder;
use App\Catalog\Application\Import\ItemImportValueNormalizer;
use App\Catalog\Domain\Item\ItemTypeEnum;
use PHPUnit\Framework\TestCase;

final class ItemImportTranslationCatalogBuilderTest extends TestCase
{
    private ItemImportTranslationCatalogBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ItemImportTranslationCatalogBuilder(new ItemImportValueNormalizer());
    }

    public function testBuildUsesEnglishFallbackToGermanForNameAndDescription(): void
    {
        $result = $this->builder->build(ItemTypeEnum::BOOK, 61, [
            'name_de' => 'Deutsch Name',
            'desc_de' => 'Deutsch Desc',
        ]);

        self::assertSame('item.book.61.name', $result->nameKey);
        self::assertSame('item.book.61.desc', $result->descKey);
        self::assertSame('Deutsch Name', $result->catalogEn['item.book.61.name']);
        self::assertSame('Deutsch Name', $result->catalogDe['item.book.61.name']);
        self::assertSame('Deutsch Desc', $result->catalogEn['item.book.61.desc']);
        self::assertSame('Deutsch Desc', $result->catalogDe['item.book.61.desc']);
    }

    public function testBuildWithoutDescriptionReturnsNullDescKey(): void
    {
        $result = $this->builder->build(ItemTypeEnum::MISC, 3, [
            'name_en' => 'Name EN',
        ]);

        self::assertSame('item.misc.3.name', $result->nameKey);
        self::assertNull($result->descKey);
        self::assertArrayHasKey('item.misc.3.name', $result->catalogEn);
        self::assertArrayNotHasKey('item.misc.3.desc', $result->catalogEn);
    }

    public function testBuildUsesDefaultNameWhenNoTranslationExists(): void
    {
        $result = $this->builder->build(ItemTypeEnum::BOOK, 999, []);

        self::assertSame('item_999', $result->catalogEn['item.book.999.name']);
        self::assertSame([], $result->catalogDe);
    }
}
