<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionOwnedPlayerApiResolver;
use App\Progression\UI\Api\ProgressionOwnedPlayerApiResolverTrait;
use App\Progression\UI\Api\ProgressionOwnedPlayerReadResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ProgressionOwnedPlayerApiResolverTraitTest extends TestCase
{
    public function testResolveOwnedPlayerOrNotFoundDelegatesToResolverWithCurrentUser(): void
    {
        $user = (new UserEntity())
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);
        $player = (new PlayerEntity())->setName('Main');

        /** @var ProgressionOwnedPlayerReadResolverInterface&MockObject $readResolver */
        $readResolver = $this->createMock(ProgressionOwnedPlayerReadResolverInterface::class);
        $readResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6', $user)
            ->willReturn($player);

        $resolver = new ProgressionOwnedPlayerApiResolver($readResolver, new ProgressionApiErrorResponder());

        $helper = new class ($resolver, $user) {
            use ProgressionOwnedPlayerApiResolverTrait;

            public function __construct(
                private readonly ProgressionOwnedPlayerApiResolver $resolver,
                private readonly UserEntity $user,
            ) {
            }

            public function resolvePlayer(string $playerId): PlayerEntity|JsonResponse
            {
                return $this->resolveOwnedPlayerOrNotFound($playerId);
            }

            protected function progressionOwnedPlayerApiResolver(): ProgressionOwnedPlayerApiResolver
            {
                return $this->resolver;
            }

            protected function getUser(): mixed
            {
                return $this->user;
            }
        };

        $result = $helper->resolvePlayer('01J5A6B7C8D9E0F1G2H3J4K5L6');
        self::assertSame($player, $result);
    }
}
