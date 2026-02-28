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

use App\Catalog\Domain\Entity\ItemEntity;
use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Application\Knowledge\ItemReadApplicationService;
use App\Progression\Application\Knowledge\ItemReadRepository;
use App\Progression\Domain\Entity\PlayerEntity;
use App\Progression\UI\Api\PlayerItemActionContext;
use App\Progression\UI\Api\PlayerItemActionContextResolver;
use App\Progression\UI\Api\PlayerOwnedContextResolver;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionItemApiResolver;
use App\Progression\UI\Api\ProgressionOwnedPlayerReadPort;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class PlayerItemActionContextResolverTest extends TestCase
{
    public function testResolveOrNotFoundReturnsContextWhenPlayerAndItemExist(): void
    {
        $user = new UserEntity()
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);
        $player = new PlayerEntity()->setName('Main');
        $item = new ItemEntity();

        /** @var ProgressionOwnedPlayerReadPort&MockObject $readResolver */
        $readResolver = $this->createMock(ProgressionOwnedPlayerReadPort::class);
        $readResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6', $user)
            ->willReturn($player);

        /** @var ItemReadRepository&MockObject $itemRepository */
        $itemRepository = $this->createMock(ItemReadRepository::class);
        $itemRepository
            ->expects(self::once())
            ->method('findOneByPublicId')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L7')
            ->willReturn($item);

        $resolver = new PlayerItemActionContextResolver(
            new PlayerOwnedContextResolver($readResolver, new ProgressionApiErrorResponder()),
            new ProgressionItemApiResolver(new ItemReadApplicationService($itemRepository), new ProgressionApiErrorResponder()),
        );

        $result = $resolver->resolveOrNotFound('01J5A6B7C8D9E0F1G2H3J4K5L6', '01J5A6B7C8D9E0F1G2H3J4K5L7', $user);
        self::assertInstanceOf(PlayerItemActionContext::class, $result);
        self::assertSame($player, $result->player);
        self::assertSame($item, $result->item);
    }

    public function testResolveOrNotFoundReturns404WhenPlayerMissing(): void
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
            ->willReturn(null);

        /** @var ItemReadRepository&MockObject $itemRepository */
        $itemRepository = $this->createMock(ItemReadRepository::class);
        $itemRepository
            ->expects(self::never())
            ->method('findOneByPublicId');

        $resolver = new PlayerItemActionContextResolver(
            new PlayerOwnedContextResolver($readResolver, new ProgressionApiErrorResponder()),
            new ProgressionItemApiResolver(new ItemReadApplicationService($itemRepository), new ProgressionApiErrorResponder()),
        );

        $result = $resolver->resolveOrNotFound('player', 'item', $user);
        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(JsonResponse::HTTP_NOT_FOUND, $result->getStatusCode());
        self::assertSame('{"error":"Player not found."}', $result->getContent());
    }

    public function testResolveOrNotFoundReturns404WhenItemMissing(): void
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
            ->willReturn($player);

        /** @var ItemReadRepository&MockObject $itemRepository */
        $itemRepository = $this->createMock(ItemReadRepository::class);
        $itemRepository
            ->expects(self::once())
            ->method('findOneByPublicId')
            ->willReturn(null);

        $resolver = new PlayerItemActionContextResolver(
            new PlayerOwnedContextResolver($readResolver, new ProgressionApiErrorResponder()),
            new ProgressionItemApiResolver(new ItemReadApplicationService($itemRepository), new ProgressionApiErrorResponder()),
        );

        $result = $resolver->resolveOrNotFound('player', 'item', $user);
        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(JsonResponse::HTTP_NOT_FOUND, $result->getStatusCode());
        self::assertSame('{"error":"Item not found."}', $result->getContent());
    }
}
