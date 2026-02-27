<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Domain\Item\ItemTypeEnum;
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
        self::assertFalse($parser->parse(123));
    }

    public function testParseReturnsEnumForValidTypeWithTrimAndCaseNormalization(): void
    {
        $parser = new ProgressionItemTypeQueryParser();

        self::assertSame(ItemTypeEnum::BOOK, $parser->parse('book'));
        self::assertSame(ItemTypeEnum::MISC, $parser->parse('  mIsC  '));
    }
}

