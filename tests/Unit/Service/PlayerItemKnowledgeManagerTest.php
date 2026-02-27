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

namespace App\Tests\Unit\Service;

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Entity\PlayerItemKnowledgeEntity;
use App\Entity\UserEntity;
use App\Progression\Application\Knowledge\PlayerItemKnowledgeFinder;
use App\Progression\Application\Knowledge\PlayerItemKnowledgeManager;
use App\Progression\Application\Player\PlayerByOwnerFinder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PlayerItemKnowledgeManagerTest extends TestCase
{
    public function testResolveOwnedPlayerUsesFinder(): void
    {
        $user = $this->createUser('owner@example.com');
        $player = $this->createPlayer($user, 'Main');
        $playerFinder = $this->createMock(PlayerByOwnerFinder::class);
        $knowledgeFinder = self::createStub(PlayerItemKnowledgeFinder::class);
        $entityManager = self::createStub(EntityManagerInterface::class);

        $manager = new PlayerItemKnowledgeManager($playerFinder, $knowledgeFinder, $entityManager);

        $playerFinder
            ->expects(self::once())
            ->method('findOneByPublicIdAndUser')
            ->with('01ARZ3NDEKTSV4RRFFQ69G5FAV', $user)
            ->willReturn($player);

        self::assertSame($player, $manager->resolveOwnedPlayer('01ARZ3NDEKTSV4RRFFQ69G5FAV', $user));
    }

    public function testSetLearnedCreatesKnowledgeWhenMissing(): void
    {
        $player = $this->createPlayer($this->createUser('owner2@example.com'), 'Main');
        $item = $this->createItem(1201);
        $playerFinder = self::createStub(PlayerByOwnerFinder::class);
        $knowledgeFinder = $this->createMock(PlayerItemKnowledgeFinder::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $manager = new PlayerItemKnowledgeManager($playerFinder, $knowledgeFinder, $entityManager);

        $knowledgeFinder
            ->expects(self::once())
            ->method('findOneByPlayerAndItem')
            ->with($player, $item)
            ->willReturn(null);

        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (mixed $value) use ($player, $item): bool {
                if (!$value instanceof PlayerItemKnowledgeEntity) {
                    return false;
                }

                return $value->getPlayer() === $player
                    && $value->getItem() === $item;
            }));

        $entityManager->expects(self::once())->method('flush');

        $manager->setLearned($player, $item);
    }

    public function testSetLearnedIsIdempotentWhenKnowledgeAlreadyExists(): void
    {
        $player = $this->createPlayer($this->createUser('owner3@example.com'), 'Main');
        $item = $this->createItem(1301);
        $playerFinder = self::createStub(PlayerByOwnerFinder::class);
        $knowledgeFinder = $this->createMock(PlayerItemKnowledgeFinder::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $manager = new PlayerItemKnowledgeManager($playerFinder, $knowledgeFinder, $entityManager);

        $existing = new PlayerItemKnowledgeEntity()
            ->setPlayer($player)
            ->setItem($item)
            ->setLearnedAt(new DateTimeImmutable('-1 day'));

        $knowledgeFinder
            ->expects(self::once())
            ->method('findOneByPlayerAndItem')
            ->with($player, $item)
            ->willReturn($existing);

        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $manager->setLearned($player, $item);
    }

    public function testUnsetLearnedRemovesKnowledgeWhenPresent(): void
    {
        $player = $this->createPlayer($this->createUser('owner4@example.com'), 'Main');
        $item = $this->createItem(1401);
        $playerFinder = self::createStub(PlayerByOwnerFinder::class);
        $knowledgeFinder = $this->createMock(PlayerItemKnowledgeFinder::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $manager = new PlayerItemKnowledgeManager($playerFinder, $knowledgeFinder, $entityManager);

        $existing = new PlayerItemKnowledgeEntity()
            ->setPlayer($player)
            ->setItem($item)
            ->setLearnedAt(new DateTimeImmutable('-1 day'));

        $knowledgeFinder
            ->expects(self::once())
            ->method('findOneByPlayerAndItem')
            ->with($player, $item)
            ->willReturn($existing);

        $entityManager->expects(self::once())->method('remove')->with($existing);
        $entityManager->expects(self::once())->method('flush');

        $manager->unsetLearned($player, $item);
    }

    public function testUnsetLearnedIsIdempotentWhenMissing(): void
    {
        $player = $this->createPlayer($this->createUser('owner5@example.com'), 'Main');
        $item = $this->createItem(1501);
        $playerFinder = self::createStub(PlayerByOwnerFinder::class);
        $knowledgeFinder = $this->createMock(PlayerItemKnowledgeFinder::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $manager = new PlayerItemKnowledgeManager($playerFinder, $knowledgeFinder, $entityManager);

        $knowledgeFinder
            ->expects(self::once())
            ->method('findOneByPlayerAndItem')
            ->with($player, $item)
            ->willReturn(null);

        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $manager->unsetLearned($player, $item);
    }

    private function createUser(string $email): UserEntity
    {
        return new UserEntity()
            ->setEmail($email)
            ->setPassword('hashed');
    }

    private function createPlayer(UserEntity $user, string $name): PlayerEntity
    {
        return new PlayerEntity()
            ->setUser($user)
            ->setName($name);
    }

    private function createItem(int $sourceId): ItemEntity
    {
        return new ItemEntity()
            ->setSourceId($sourceId)
            ->setType(ItemTypeEnum::BOOK)
            ->setNameKey(sprintf('item.book.%d.name', $sourceId));
    }
}
