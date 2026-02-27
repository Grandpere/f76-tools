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
use App\Progression\Application\Player\PlayerReadApplicationService;
use App\Progression\Application\Player\PlayerReadRepositoryInterface;
use App\Progression\Domain\Entity\PlayerEntity;
use App\Progression\UI\Api\ProgressionOwnedPlayerReadResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProgressionOwnedPlayerReadResolverTest extends TestCase
{
    public function testResolveDelegatesToPlayerReadServiceWithUserEntity(): void
    {
        $user = new UserEntity()
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);
        $player = new PlayerEntity()->setName('Main');

        /** @var PlayerReadRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(PlayerReadRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('findOneByPublicIdAndUser')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6', $user)
            ->willReturn($player);

        $playerReadService = new PlayerReadApplicationService($repository);
        $resolver = new ProgressionOwnedPlayerReadResolver($playerReadService);
        $resolved = $resolver->resolve('01J5A6B7C8D9E0F1G2H3J4K5L6', $user);

        self::assertSame($player, $resolved);
    }
}
