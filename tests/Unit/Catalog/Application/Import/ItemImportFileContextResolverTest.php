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

use App\Catalog\Application\Import\ItemImportFileContextResolver;
use App\Catalog\Domain\Item\ItemTypeEnum;
use PHPUnit\Framework\TestCase;

final class ItemImportFileContextResolverTest extends TestCase
{
    public function testResolveReturnsMiscContextForLegendaryModFile(): void
    {
        $resolver = new ItemImportFileContextResolver();

        $context = $resolver->resolve('/tmp/legendary_mods_3_en.json');

        self::assertNotNull($context);
        self::assertSame(ItemTypeEnum::MISC, $context->type);
        self::assertSame(3, $context->rank);
        self::assertNull($context->listNumber);
        self::assertFalse($context->isSpecialList);
        self::assertSame('nukaknights', $context->sourceProvider);
    }

    public function testResolveReturnsBookContextForMinervaRegularAndSpecialFiles(): void
    {
        $resolver = new ItemImportFileContextResolver();

        $regular = $resolver->resolve('/tmp/minerva_61_en.json');
        self::assertNotNull($regular);
        self::assertSame(ItemTypeEnum::BOOK, $regular->type);
        self::assertNull($regular->rank);
        self::assertSame(1, $regular->listNumber);
        self::assertFalse($regular->isSpecialList);
        self::assertSame('nukaknights', $regular->sourceProvider);

        $special = $resolver->resolve('/tmp/minerva_64_en.json');
        self::assertNotNull($special);
        self::assertSame(4, $special->listNumber);
        self::assertTrue($special->isSpecialList);

        $last = $resolver->resolve('/tmp/minerva_84_en.json');
        self::assertNotNull($last);
        self::assertSame(24, $last->listNumber);
        self::assertTrue($last->isSpecialList);
    }

    public function testResolveReturnsNullForUnsupportedFileNames(): void
    {
        $resolver = new ItemImportFileContextResolver();

        self::assertNull($resolver->resolve('/tmp/other_file.json'));
    }

    public function testResolveReturnsBookCatalogContextForFandomAndFalloutWikiFiles(): void
    {
        $resolver = new ItemImportFileContextResolver();

        $fandom = $resolver->resolve('/tmp/project/data/sources/fandom/plan_recipes/recipes.json');
        self::assertNotNull($fandom);
        self::assertSame(ItemTypeEnum::BOOK, $fandom->type);
        self::assertNull($fandom->rank);
        self::assertNull($fandom->listNumber);
        self::assertFalse($fandom->isSpecialList);
        self::assertSame('fandom', $fandom->sourceProvider);

        $falloutWiki = $resolver->resolve('/tmp/project/data/sources/fallout_wiki/plan_recipes/plans_weapons.json');
        self::assertNotNull($falloutWiki);
        self::assertSame(ItemTypeEnum::BOOK, $falloutWiki->type);
        self::assertNull($falloutWiki->rank);
        self::assertNull($falloutWiki->listNumber);
        self::assertFalse($falloutWiki->isSpecialList);
        self::assertSame('fallout_wiki', $falloutWiki->sourceProvider);
    }
}
