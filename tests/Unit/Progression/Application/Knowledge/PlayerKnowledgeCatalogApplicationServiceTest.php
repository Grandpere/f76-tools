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

use App\Contract\ItemKnowledgeCatalogReadRepositoryInterface;
use App\Contract\PlayerKnowledgeCatalogReadRepositoryInterface;
use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Progression\Application\Knowledge\PlayerKnowledgeCatalogApplicationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PlayerKnowledgeCatalogApplicationServiceTest extends TestCase
{
    public function testListForPlayerMarksLearnedItems(): void
    {
        $player = new PlayerEntity();
        $bookA = $this->createItemWithId(101, ItemTypeEnum::BOOK, 10, 'catalog.book.a');
        $bookB = $this->createItemWithId(102, ItemTypeEnum::BOOK, 11, 'catalog.book.b');

        /** @var ItemKnowledgeCatalogReadRepositoryInterface&MockObject $itemRepository */
        $itemRepository = $this->createMock(ItemKnowledgeCatalogReadRepositoryInterface::class);
        $itemRepository
            ->expects(self::once())
            ->method('findAllOrdered')
            ->with(ItemTypeEnum::BOOK)
            ->willReturn([$bookA, $bookB]);

        /** @var PlayerKnowledgeCatalogReadRepositoryInterface&MockObject $knowledgeRepository */
        $knowledgeRepository = $this->createMock(PlayerKnowledgeCatalogReadRepositoryInterface::class);
        $knowledgeRepository
            ->expects(self::once())
            ->method('findLearnedItemIdsByPlayer')
            ->with($player)
            ->willReturn([102]);

        $service = new PlayerKnowledgeCatalogApplicationService($itemRepository, $knowledgeRepository);
        $rows = $service->listForPlayer($player, ItemTypeEnum::BOOK);

        self::assertCount(2, $rows);
        self::assertSame($bookA, $rows[0]['item']);
        self::assertFalse($rows[0]['learned']);
        self::assertSame($bookB, $rows[1]['item']);
        self::assertTrue($rows[1]['learned']);
    }

    public function testListForPlayerPassesNullTypeToRepository(): void
    {
        $player = new PlayerEntity();
        $misc = $this->createItemWithId(201, ItemTypeEnum::MISC, 20, 'catalog.misc');

        /** @var ItemKnowledgeCatalogReadRepositoryInterface&MockObject $itemRepository */
        $itemRepository = $this->createMock(ItemKnowledgeCatalogReadRepositoryInterface::class);
        $itemRepository
            ->expects(self::once())
            ->method('findAllOrdered')
            ->with(null)
            ->willReturn([$misc]);

        /** @var PlayerKnowledgeCatalogReadRepositoryInterface&MockObject $knowledgeRepository */
        $knowledgeRepository = $this->createMock(PlayerKnowledgeCatalogReadRepositoryInterface::class);
        $knowledgeRepository
            ->expects(self::once())
            ->method('findLearnedItemIdsByPlayer')
            ->with($player)
            ->willReturn([]);

        $service = new PlayerKnowledgeCatalogApplicationService($itemRepository, $knowledgeRepository);
        $rows = $service->listForPlayer($player, null);

        self::assertCount(1, $rows);
        self::assertSame($misc, $rows[0]['item']);
        self::assertFalse($rows[0]['learned']);
    }

    private function createItemWithId(int $id, ItemTypeEnum $type, int $sourceId, string $nameKey): ItemEntity
    {
        $item = new ItemEntity()
            ->setType($type)
            ->setSourceId($sourceId)
            ->setNameKey($nameKey);

        $reflection = new ReflectionProperty(ItemEntity::class, 'id');
        $reflection->setValue($item, $id);

        return $item;
    }
}
