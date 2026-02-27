<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\Application\Knowledge\OwnedPlayerResolverInterface;
use App\Progression\UI\Api\ProgressionApiUserContext;
use App\Progression\UI\Api\ProgressionOwnedPlayerResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProgressionOwnedPlayerResolverTest extends TestCase
{
    public function testResolveDelegatesToOwnedPlayerResolverWithAuthenticatedUser(): void
    {
        $user = (new UserEntity())
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);
        $player = (new PlayerEntity())->setName('Main');

        /** @var OwnedPlayerResolverInterface&MockObject $ownedPlayerResolver */
        $ownedPlayerResolver = $this->createMock(OwnedPlayerResolverInterface::class);
        $ownedPlayerResolver
            ->expects(self::once())
            ->method('resolveOwnedPlayer')
            ->with($user, '01J5A6B7C8D9E0F1G2H3J4K5L6')
            ->willReturn($player);

        $resolver = new ProgressionOwnedPlayerResolver(new ProgressionApiUserContext(), $ownedPlayerResolver);
        $resolved = $resolver->resolve('01J5A6B7C8D9E0F1G2H3J4K5L6', $user);

        self::assertSame($player, $resolved);
    }
}

