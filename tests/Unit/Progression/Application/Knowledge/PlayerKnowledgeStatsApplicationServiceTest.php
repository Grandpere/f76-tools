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

namespace App\Tests\Unit\Progression\Application\Knowledge;

use App\Domain\Item\ItemTypeEnum;
use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\Application\Knowledge\ItemStatsReadRepository;
use App\Progression\Application\Knowledge\PlayerKnowledgeStatsApplicationService;
use App\Progression\Application\Knowledge\PlayerKnowledgeStatsReadRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PlayerKnowledgeStatsApplicationServiceTest extends TestCase
{
    public function testGetStatsBuildsExpectedPayload(): void
    {
        /** @var ItemStatsReadRepository&MockObject $itemRepository */
        $itemRepository = $this->createMock(ItemStatsReadRepository::class);
        /** @var PlayerKnowledgeStatsReadRepository&MockObject $knowledgeRepository */
        $knowledgeRepository = $this->createMock(PlayerKnowledgeStatsReadRepository::class);

        $player = $this->createPlayer('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $service = new PlayerKnowledgeStatsApplicationService($itemRepository, $knowledgeRepository);

        $itemRepository->method('countAll')->willReturn(10);
        $itemRepository
            ->method('countByType')
            ->willReturnMap([
                [ItemTypeEnum::MISC, 4],
                [ItemTypeEnum::BOOK, 6],
            ]);
        $itemRepository->method('findMiscTotalsByRank')->willReturn([1 => 2, 2 => 2]);
        $itemRepository->method('findBookTotalsByListNumber')->willReturn([1 => 3, 4 => 3]);

        $knowledgeRepository->method('countLearnedByPlayer')->willReturn(5);
        $knowledgeRepository
            ->method('countLearnedByPlayerAndType')
            ->willReturnMap([
                [$player, ItemTypeEnum::MISC, 1],
                [$player, ItemTypeEnum::BOOK, 4],
            ]);
        $knowledgeRepository->method('findLearnedMiscCountsByRank')->willReturn([1 => 1, 2 => 0]);
        $knowledgeRepository->method('findLearnedBookCountsByListNumber')->willReturn([1 => 2, 4 => 2]);

        $payload = $service->getStats($player);

        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $payload['playerId']);
        self::assertSame(['learned' => 5, 'total' => 10, 'percent' => 50], $payload['overall']);
        self::assertSame(['learned' => 1, 'total' => 4, 'percent' => 25], $payload['byType']['misc']);
        self::assertSame(['learned' => 4, 'total' => 6, 'percent' => 67], $payload['byType']['book']);

        self::assertSame(
            [
                ['rank' => 1, 'learned' => 1, 'total' => 2, 'percent' => 50],
                ['rank' => 2, 'learned' => 0, 'total' => 2, 'percent' => 0],
            ],
            $payload['miscByRank'],
        );
        self::assertSame(
            [
                ['listNumber' => 1, 'learned' => 2, 'total' => 3, 'percent' => 67],
                ['listNumber' => 4, 'learned' => 2, 'total' => 3, 'percent' => 67],
            ],
            $payload['bookByList'],
        );
    }

    private function createPlayer(string $publicId): PlayerEntity
    {
        $user = new UserEntity()
            ->setEmail('owner@example.com')
            ->setPassword('hashed');

        $player = new PlayerEntity()
            ->setUser($user)
            ->setName('Main');

        $reflection = new ReflectionClass($player);
        $property = $reflection->getProperty('publicId');
        $property->setValue($player, $publicId);

        return $player;
    }
}
