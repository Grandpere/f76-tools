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

use App\Progression\UI\Api\ProgressionApiJsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ProgressionApiJsonPayloadDecoderTest extends TestCase
{
    public function testDecodeReturnsNormalizedArrayForValidJson(): void
    {
        $decoder = new ProgressionApiJsonPayloadDecoder();
        $request = new Request(content: json_encode(['name' => 'Main', 'nested' => ['a' => 1]], JSON_THROW_ON_ERROR));

        $payload = $decoder->decode($request);

        self::assertSame([
            'name' => 'Main',
            'nested' => ['a' => 1],
        ], $payload);
    }

    public function testDecodeReturnsEmptyArrayForInvalidJsonOrNonArrayPayload(): void
    {
        $decoder = new ProgressionApiJsonPayloadDecoder();

        $invalidJson = new Request(content: '{invalid');
        self::assertSame([], $decoder->decode($invalidJson));

        $nonArray = new Request(content: json_encode('string-payload', JSON_THROW_ON_ERROR));
        self::assertSame([], $decoder->decode($nonArray));
    }
}
