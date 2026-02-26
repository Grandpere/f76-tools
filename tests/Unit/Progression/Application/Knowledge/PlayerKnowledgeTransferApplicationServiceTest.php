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

use App\Contract\ItemKnowledgeTransferRepositoryInterface;
use App\Contract\PlayerKnowledgeTransferRepositoryInterface;
use App\Domain\Item\ItemTypeEnum;
use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\Application\Knowledge\PlayerKnowledgeTransferApplicationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PlayerKnowledgeTransferApplicationServiceTest extends TestCase
{
    public function testPreviewImportComputesDiffAndUnknownItems(): void
    {
        $player = $this->createPlayer();
        /** @var PlayerKnowledgeTransferRepositoryInterface&MockObject $knowledgeRepository */
        $knowledgeRepository = $this->createMock(PlayerKnowledgeTransferRepositoryInterface::class);
        /** @var ItemKnowledgeTransferRepositoryInterface&MockObject $itemRepository */
        $itemRepository = $this->createMock(ItemKnowledgeTransferRepositoryInterface::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $service = new PlayerKnowledgeTransferApplicationService($knowledgeRepository, $itemRepository, $entityManager);

        $knowledgeRepository
            ->expects(self::once())
            ->method('findLearnedItemIdsByPlayer')
            ->with($player)
            ->willReturn([10]);

        $itemRepository
            ->expects(self::once())
            ->method('findByTypeAndSourceIds')
            ->with(ItemTypeEnum::BOOK, [901])
            ->willReturn([]);

        $result = $service->previewImport($player, [
            'version' => 1,
            'replace' => true,
            'learnedItems' => [
                ['type' => 'BOOK', 'sourceId' => 901],
            ],
        ]);

        self::assertTrue($result['ok']);
        self::assertArrayHasKey('wouldAdd', $result);
        self::assertArrayHasKey('wouldRemove', $result);
        self::assertArrayHasKey('unknownItems', $result);
        self::assertSame(0, $result['wouldAdd']);
        self::assertSame(1, $result['wouldRemove']);
        self::assertSame([['type' => 'BOOK', 'sourceId' => 901]], $result['unknownItems']);
    }

    public function testImportFailsWhenUnknownItemsArePresent(): void
    {
        $player = $this->createPlayer();
        /** @var PlayerKnowledgeTransferRepositoryInterface&MockObject $knowledgeRepository */
        $knowledgeRepository = $this->createMock(PlayerKnowledgeTransferRepositoryInterface::class);
        /** @var ItemKnowledgeTransferRepositoryInterface&MockObject $itemRepository */
        $itemRepository = $this->createMock(ItemKnowledgeTransferRepositoryInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new PlayerKnowledgeTransferApplicationService($knowledgeRepository, $itemRepository, $entityManager);

        $knowledgeRepository
            ->expects(self::once())
            ->method('findLearnedItemIdsByPlayer')
            ->with($player)
            ->willReturn([10]);

        $itemRepository
            ->expects(self::once())
            ->method('findByTypeAndSourceIds')
            ->with(ItemTypeEnum::MISC, [1234])
            ->willReturn([]);

        $knowledgeRepository->expects(self::never())->method('deleteByPlayerAndItemIds');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $result = $service->import($player, [
            'version' => 1,
            'replace' => true,
            'learnedItems' => [
                ['type' => 'MISC', 'sourceId' => 1234],
            ],
        ]);

        self::assertFalse($result['ok']);
        self::assertArrayHasKey('error', $result);
        self::assertArrayHasKey('unknownItems', $result);
        if (!isset($result['unknownItems'])) {
            self::fail('unknownItems key is required on unknown payload errors.');
        }
        self::assertSame('Unknown items in payload.', $result['error']);
        self::assertSame([['type' => 'MISC', 'sourceId' => 1234]], $result['unknownItems']);
    }

    private function createPlayer(): PlayerEntity
    {
        $user = (new UserEntity())
            ->setEmail('owner@example.com')
            ->setPassword('hashed');

        return (new PlayerEntity())
            ->setUser($user)
            ->setName('Main');
    }
}
