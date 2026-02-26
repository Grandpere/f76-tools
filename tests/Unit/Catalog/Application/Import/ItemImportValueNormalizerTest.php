<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Import;

use App\Catalog\Application\Import\ItemImportValueNormalizer;
use PHPUnit\Framework\TestCase;

final class ItemImportValueNormalizerTest extends TestCase
{
    private ItemImportValueNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ItemImportValueNormalizer();
    }

    public function testToNullableString(): void
    {
        self::assertNull($this->normalizer->toNullableString(null));
        self::assertNull($this->normalizer->toNullableString('   '));
        self::assertSame('42', $this->normalizer->toNullableString(42));
        self::assertSame('abc', $this->normalizer->toNullableString(' abc '));
    }

    public function testToNullableInt(): void
    {
        self::assertNull($this->normalizer->toNullableInt(null));
        self::assertNull($this->normalizer->toNullableInt(''));
        self::assertNull($this->normalizer->toNullableInt('abc'));
        self::assertSame(12, $this->normalizer->toNullableInt('12'));
    }

    public function testToBool(): void
    {
        self::assertTrue($this->normalizer->toBool(true));
        self::assertTrue($this->normalizer->toBool(1));
        self::assertTrue($this->normalizer->toBool('yes'));
        self::assertFalse($this->normalizer->toBool('no'));
        self::assertFalse($this->normalizer->toBool(null));
    }

    public function testToBoolFromRowAny(): void
    {
        $row = ['a' => 0, 'b' => 'true'];

        self::assertTrue($this->normalizer->toBoolFromRowAny($row, ['a', 'b']));
        self::assertFalse($this->normalizer->toBoolFromRowAny($row, ['a']));
        self::assertFalse($this->normalizer->toBoolFromRowAny($row, ['c']));
    }

    public function testNormalizePayload(): void
    {
        $payload = $this->normalizer->normalizePayload(['id' => 1, 2 => 'value']);

        self::assertArrayHasKey('2', $payload);
        self::assertContains('value', $payload);
    }
}
