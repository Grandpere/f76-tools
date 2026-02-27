<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Progression\UI\Api\PlayerKnowledgeTransferResultResponder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class PlayerKnowledgeTransferResultResponderTest extends TestCase
{
    public function testRespondReturnsBadRequestWhenResultIsNotOk(): void
    {
        $responder = new PlayerKnowledgeTransferResultResponder();
        $response = $responder->respond([
            'ok' => false,
            'error' => 'Invalid payload',
        ]);

        self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('{"ok":false,"error":"Invalid payload"}', $response->getContent());
    }

    public function testRespondReturnsOkWhenResultIsOk(): void
    {
        $responder = new PlayerKnowledgeTransferResultResponder();
        $response = $responder->respond([
            'ok' => true,
            'added' => 2,
            'removed' => 1,
        ]);

        self::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"ok":true,"added":2,"removed":1}', $response->getContent());
    }
}

