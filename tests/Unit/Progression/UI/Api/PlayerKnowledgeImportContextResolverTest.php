<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\UI\Api\PlayerKnowledgeImportContext;
use App\Progression\UI\Api\PlayerKnowledgeImportContextResolver;
use App\Progression\UI\Api\PlayerOwnedContextResolver;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionApiJsonPayloadDecoder;
use App\Progression\UI\Api\ProgressionOwnedPlayerApiResolver;
use App\Progression\UI\Api\ProgressionOwnedPlayerReadResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class PlayerKnowledgeImportContextResolverTest extends TestCase
{
    public function testResolveOrNotFoundReturnsContextWhenPlayerExists(): void
    {
        $user = (new UserEntity())
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);
        $player = (new PlayerEntity())->setName('Main');
        $request = new Request(content: '{"replace":false,"learnedItems":[{"type":"BOOK","sourceId":901}]}');

        /** @var ProgressionOwnedPlayerReadResolverInterface&MockObject $readResolver */
        $readResolver = $this->createMock(ProgressionOwnedPlayerReadResolverInterface::class);
        $readResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6', $user)
            ->willReturn($player);

        $resolver = new PlayerKnowledgeImportContextResolver(
            new PlayerOwnedContextResolver(new ProgressionOwnedPlayerApiResolver($readResolver, new ProgressionApiErrorResponder())),
            new ProgressionApiJsonPayloadDecoder(),
        );

        $result = $resolver->resolveOrNotFound('01J5A6B7C8D9E0F1G2H3J4K5L6', $request, $user);
        self::assertInstanceOf(PlayerKnowledgeImportContext::class, $result);
        self::assertSame($player, $result->player);
        self::assertSame(false, $result->payload['replace']);
    }

    public function testResolveOrNotFoundReturns404WhenPlayerMissing(): void
    {
        $user = (new UserEntity())
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);
        $request = new Request(content: '{"learnedItems":[]}');

        /** @var ProgressionOwnedPlayerReadResolverInterface&MockObject $readResolver */
        $readResolver = $this->createMock(ProgressionOwnedPlayerReadResolverInterface::class);
        $readResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6', $user)
            ->willReturn(null);

        $resolver = new PlayerKnowledgeImportContextResolver(
            new PlayerOwnedContextResolver(new ProgressionOwnedPlayerApiResolver($readResolver, new ProgressionApiErrorResponder())),
            new ProgressionApiJsonPayloadDecoder(),
        );

        $result = $resolver->resolveOrNotFound('01J5A6B7C8D9E0F1G2H3J4K5L6', $request, $user);
        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(JsonResponse::HTTP_NOT_FOUND, $result->getStatusCode());
        self::assertSame('{"error":"Player not found."}', $result->getContent());
    }
}
