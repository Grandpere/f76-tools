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

namespace App\Tests\Unit\Progression\UI\Api;

use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Progression\UI\Api\ProgressionItemTypeQueryParser;
use PHPUnit\Framework\TestCase;

final class ProgressionItemTypeQueryParserTest extends TestCase
{
    public function testParseReturnsNullForMissingValue(): void
    {
        $parser = new ProgressionItemTypeQueryParser();

        self::assertNull($parser->parse(null));
        self::assertNull($parser->parse(''));
    }

    public function testParseReturnsFalseForInvalidType(): void
    {
        $parser = new ProgressionItemTypeQueryParser();

        self::assertFalse($parser->parse('invalid'));
        self::assertFalse($parser->parse('   '));
        self::assertFalse($parser->parse(123));
    }

    public function testParseReturnsEnumForValidTypeWithTrimAndCaseNormalization(): void
    {
        $parser = new ProgressionItemTypeQueryParser();

        self::assertSame(ItemTypeEnum::BOOK, $parser->parse('book'));
        self::assertSame(ItemTypeEnum::MISC, $parser->parse('  mIsC  '));
    }
}
