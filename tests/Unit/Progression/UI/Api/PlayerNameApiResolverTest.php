<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Progression\UI\Api\PlayerNameApiResolver;
use App\Progression\UI\Api\PlayerNameRequestExtractor;
use App\Progression\UI\Api\PlayerControllerWriteResponder;
use App\Progression\UI\Api\PlayerPayloadMapper;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionApiJsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class PlayerNameApiResolverTest extends TestCase
{
    public function testResolveOrInvalidReturnsNameWhenExtractorSucceeds(): void
    {
        $request = new Request(content: '{"name":"Main"}');

        $resolver = new PlayerNameApiResolver(
            new PlayerNameRequestExtractor(new ProgressionApiJsonPayloadDecoder()),
            new PlayerControllerWriteResponder(new PlayerPayloadMapper(), new ProgressionApiErrorResponder()),
        );
        $result = $resolver->resolveOrInvalid($request);

        self::assertSame('Main', $result);
    }

    public function testResolveOrInvalidReturnsErrorResponseWhenExtractorFails(): void
    {
        $request = new Request(content: '{}');

        $resolver = new PlayerNameApiResolver(
            new PlayerNameRequestExtractor(new ProgressionApiJsonPayloadDecoder()),
            new PlayerControllerWriteResponder(new PlayerPayloadMapper(), new ProgressionApiErrorResponder()),
        );
        $result = $resolver->resolveOrInvalid($request);

        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $result->getStatusCode());
        self::assertSame('{"error":"Invalid player name."}', $result->getContent());
    }
}
