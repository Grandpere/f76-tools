<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Progression\UI\Api\PlayerNameRequestExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PlayerNameRequestExtractorTest extends TestCase
{
    public function testExtractReturnsTrimmedNameFromValidJsonPayload(): void
    {
        $extractor = new PlayerNameRequestExtractor();
        $request = new Request(content: json_encode(['name' => '  Main Character  '], JSON_THROW_ON_ERROR));

        $result = $extractor->extract($request);

        self::assertSame('Main Character', $result);
    }

    public function testExtractReturnsNullForInvalidPayloads(): void
    {
        $extractor = new PlayerNameRequestExtractor();

        $invalidJson = new Request(content: '{invalid');
        self::assertNull($extractor->extract($invalidJson));

        $missingName = new Request(content: json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR));
        self::assertNull($extractor->extract($missingName));

        $nonStringName = new Request(content: json_encode(['name' => 123], JSON_THROW_ON_ERROR));
        self::assertNull($extractor->extract($nonStringName));

        $blankName = new Request(content: json_encode(['name' => '   '], JSON_THROW_ON_ERROR));
        self::assertNull($extractor->extract($blankName));
    }
}

