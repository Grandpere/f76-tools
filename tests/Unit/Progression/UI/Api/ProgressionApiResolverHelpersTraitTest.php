<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\Application\Knowledge\ItemReadApplicationService;
use App\Progression\Application\Knowledge\ItemReadRepositoryInterface;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionApiResolverHelpersTrait;
use App\Progression\UI\Api\ProgressionItemApiResolver;
use App\Progression\UI\Api\ProgressionOwnedPlayerApiResolver;
use App\Progression\UI\Api\ProgressionOwnedPlayerReadResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ProgressionApiResolverHelpersTraitTest extends TestCase
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

        $helper = new class ($user) {
            use ProgressionApiResolverHelpersTrait;

            public function __construct(private readonly UserEntity $user)
            {
            }

            public function resolvePlayer(ProgressionOwnedPlayerApiResolver $resolver, string $playerId): PlayerEntity|JsonResponse
            {
                return $this->resolveOwnedPlayerOrNotFound($resolver, $playerId);
            }

            protected function getUser(): mixed
            {
                return $this->user;
            }
        };

        $resolver = new ProgressionOwnedPlayerApiResolver($readResolver, new ProgressionApiErrorResponder());
        $result = $helper->resolvePlayer($resolver, '01J5A6B7C8D9E0F1G2H3J4K5L6');

        self::assertSame($player, $result);
    }

    public function testResolveItemOrNotFoundDelegatesToResolver(): void
    {
        $item = new ItemEntity();

        /** @var ItemReadRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(ItemReadRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('findOneByPublicId')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6')
            ->willReturn($item);

        $helper = new class () {
            use ProgressionApiResolverHelpersTrait;

            public function resolveItem(ProgressionItemApiResolver $resolver, string $itemId): ItemEntity|JsonResponse
            {
                return $this->resolveItemOrNotFound($resolver, $itemId);
            }

            protected function getUser(): mixed
            {
                return null;
            }
        };

        $resolver = new ProgressionItemApiResolver(
            new ItemReadApplicationService($repository),
            new ProgressionApiErrorResponder(),
        );
        $result = $helper->resolveItem($resolver, '01J5A6B7C8D9E0F1G2H3J4K5L6');

        self::assertSame($item, $result);
    }
}
