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

        self::assertIsArray($context);
        self::assertSame(ItemTypeEnum::MISC, $context['type']);
        self::assertSame(3, $context['rank']);
        self::assertNull($context['listNumber']);
        self::assertFalse($context['isSpecialList']);
    }

    public function testResolveReturnsBookContextForMinervaRegularAndSpecialFiles(): void
    {
        $resolver = new ItemImportFileContextResolver();

        $regular = $resolver->resolve('/tmp/minerva_61_en.json');
        self::assertIsArray($regular);
        self::assertSame(ItemTypeEnum::BOOK, $regular['type']);
        self::assertNull($regular['rank']);
        self::assertSame(1, $regular['listNumber']);
        self::assertFalse($regular['isSpecialList']);

        $special = $resolver->resolve('/tmp/minerva_64_en.json');
        self::assertIsArray($special);
        self::assertSame(4, $special['listNumber']);
        self::assertTrue($special['isSpecialList']);
    }

    public function testResolveReturnsNullForUnsupportedFileNames(): void
    {
        $resolver = new ItemImportFileContextResolver();

        self::assertNull($resolver->resolve('/tmp/other_file.json'));
    }
}
