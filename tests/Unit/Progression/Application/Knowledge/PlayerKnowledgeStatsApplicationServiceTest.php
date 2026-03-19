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

use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Application\Knowledge\ItemStatsReadRepository;
use App\Progression\Application\Knowledge\PlayerKnowledgeStatsApplicationService;
use App\Progression\Application\Knowledge\PlayerKnowledgeStatsReadRepository;
use App\Progression\Domain\Entity\PlayerEntity;
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

        $itemRepository->method('countAllByType')->willReturn([
            'all' => 10,
            'misc' => 4,
            'book' => 6,
        ]);
        $itemRepository->method('findMiscTotalsByRank')->willReturn([1 => 2, 2 => 2]);
        $itemRepository->method('findBookTotalsByListNumber')->willReturn([1 => 3, 4 => 3]);
        $itemRepository->method('countBooksWithListNumber')->willReturn(4);
        $itemRepository->method('findBookTotalsByKind')->willReturn(['plan' => 4, 'recipe' => 2]);
        $itemRepository->method('findBookTotalsByCategory')->willReturn([
            'weapon_plan' => 2,
            'workshop_plan' => 2,
            'recipe' => 2,
        ]);
        $itemRepository->method('findBookTotalsBySubcategory')->willReturn([
            'weapon_plan' => ['ballistic' => 2],
            'workshop_plan' => ['floor_decor' => 2],
        ]);
        $itemRepository->method('findBookTotalsByDetail')->willReturn([
            'workshop_plan' => ['beds' => 1],
            'recipe' => ['chems' => 2],
        ]);

        $knowledgeRepository->method('countLearnedByPlayerByType')->willReturn([
            'all' => 5,
            'misc' => 1,
            'book' => 4,
        ]);
        $knowledgeRepository->method('findLearnedMiscCountsByRank')->willReturn([1 => 1, 2 => 0]);
        $knowledgeRepository->method('findLearnedBookCountsByListNumber')->willReturn([1 => 2, 4 => 2]);
        $knowledgeRepository->method('countLearnedBooksWithListNumber')->willReturn(2);
        $knowledgeRepository->method('findLearnedBookCountsByKind')->willReturn(['plan' => 3, 'recipe' => 1]);
        $knowledgeRepository->method('findLearnedBookCountsByCategory')->willReturn([
            'weapon_plan' => 1,
            'workshop_plan' => 2,
            'recipe' => 1,
        ]);
        $knowledgeRepository->method('findLearnedBookCountsBySubcategory')->willReturn([
            'weapon_plan' => ['ballistic' => 1],
            'workshop_plan' => ['floor_decor' => 2],
        ]);
        $knowledgeRepository->method('findLearnedBookCountsByDetail')->willReturn([
            'workshop_plan' => ['beds' => 1],
            'recipe' => ['chems' => 1],
        ]);

        $payload = $service->getStats($player);

        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $payload['playerId']);
        self::assertSame(['learned' => 5, 'total' => 10, 'percent' => 50], $payload['overall']);
        self::assertSame(['learned' => 1, 'total' => 4, 'percent' => 25], $payload['byType']['misc']);
        self::assertSame(['learned' => 4, 'total' => 6, 'percent' => 67], $payload['byType']['book']);
        self::assertSame(['learned' => 2, 'total' => 4, 'percent' => 50], $payload['minervaBooks']);
        self::assertSame(['learned' => 3, 'total' => 4, 'percent' => 75], $payload['byBookKind']['plan']);
        self::assertSame(['learned' => 1, 'total' => 2, 'percent' => 50], $payload['byBookKind']['recipe']);
        self::assertSame(
            [
                ['category' => 'weapon_plan', 'learned' => 1, 'total' => 2, 'percent' => 50],
                ['category' => 'workshop_plan', 'learned' => 2, 'total' => 2, 'percent' => 100],
                ['category' => 'recipe', 'learned' => 1, 'total' => 2, 'percent' => 50],
            ],
            $payload['bookByCategory'],
        );
        self::assertSame(
            [
                ['category' => 'weapon_plan', 'subcategory' => 'ballistic', 'label' => 'Ballistic', 'learned' => 1, 'total' => 2, 'percent' => 50],
                ['category' => 'workshop_plan', 'subcategory' => 'floor_decor', 'label' => 'Floor Decor', 'learned' => 2, 'total' => 2, 'percent' => 100],
            ],
            $payload['bookBySubcategory'],
        );
        self::assertSame(
            [
                ['category' => 'workshop_plan', 'detail' => 'beds', 'label' => 'Beds', 'learned' => 1, 'total' => 1, 'percent' => 100],
                ['category' => 'recipe', 'detail' => 'chems', 'label' => 'Chems', 'learned' => 1, 'total' => 2, 'percent' => 50],
            ],
            $payload['bookByDetail'],
        );

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
