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

use App\Progression\UI\Api\ProgressionApiErrorResponder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ProgressionApiErrorResponderTest extends TestCase
{
    public function testPlayerNotFoundResponse(): void
    {
        $responder = new ProgressionApiErrorResponder();
        $response = $responder->playerNotFound();

        self::assertSame(JsonResponse::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('{"error":"Player not found."}', $response->getContent());
    }

    public function testItemNotFoundResponse(): void
    {
        $responder = new ProgressionApiErrorResponder();
        $response = $responder->itemNotFound();

        self::assertSame(JsonResponse::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('{"error":"Item not found."}', $response->getContent());
    }

    public function testInvalidItemTypeResponse(): void
    {
        $responder = new ProgressionApiErrorResponder();
        $response = $responder->invalidItemType();

        self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('{"error":"Invalid item type."}', $response->getContent());
    }

    public function testInvalidPlayerNameResponse(): void
    {
        $responder = new ProgressionApiErrorResponder();
        $response = $responder->invalidPlayerName();

        self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('{"error":"Invalid player name."}', $response->getContent());
    }

    public function testPlayerNameAlreadyExistsResponse(): void
    {
        $responder = new ProgressionApiErrorResponder();
        $response = $responder->playerNameAlreadyExists();

        self::assertSame(JsonResponse::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame('{"error":"Player name already exists."}', $response->getContent());
    }
}
