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

use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Domain\Entity\PlayerEntity;
use App\Progression\UI\Api\PlayerOwnedContextResolver;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionOwnedPlayerReadPort;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class PlayerOwnedContextResolverTest extends TestCase
{
    public function testResolveOrNotFoundReturnsPlayerWhenFound(): void
    {
        $user = new UserEntity()
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);
        $player = new PlayerEntity()->setName('Main');

        /** @var ProgressionOwnedPlayerReadPort&MockObject $readResolver */
        $readResolver = $this->createMock(ProgressionOwnedPlayerReadPort::class);
        $readResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6', $user)
            ->willReturn($player);

        $resolver = new PlayerOwnedContextResolver(
            $readResolver,
            new ProgressionApiErrorResponder(),
        );

        $result = $resolver->resolveOrNotFound('01J5A6B7C8D9E0F1G2H3J4K5L6', $user);
        self::assertSame($player, $result);
    }

    public function testResolveOrNotFoundReturns404WhenMissing(): void
    {
        $user = new UserEntity()
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);

        /** @var ProgressionOwnedPlayerReadPort&MockObject $readResolver */
        $readResolver = $this->createMock(ProgressionOwnedPlayerReadPort::class);
        $readResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('missing-player', $user)
            ->willReturn(null);

        $resolver = new PlayerOwnedContextResolver(
            $readResolver,
            new ProgressionApiErrorResponder(),
        );

        $result = $resolver->resolveOrNotFound('missing-player', $user);
        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(JsonResponse::HTTP_NOT_FOUND, $result->getStatusCode());
        self::assertSame('{"error":"Player not found."}', $result->getContent());
    }
}
