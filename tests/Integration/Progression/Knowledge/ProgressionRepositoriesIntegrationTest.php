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

namespace App\Tests\Integration\Progression\Knowledge;

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemBookListEntity;
use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Entity\PlayerItemKnowledgeEntity;
use App\Entity\UserEntity;
use App\Repository\ItemEntityRepository;
use App\Repository\PlayerItemKnowledgeEntityRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProgressionRepositoriesIntegrationTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?ItemEntityRepository $itemRepository = null;
    private ?PlayerItemKnowledgeEntityRepository $knowledgeRepository = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $itemRepository = $container->get(ItemEntityRepository::class);
        \assert($itemRepository instanceof ItemEntityRepository);
        $this->itemRepository = $itemRepository;

        $knowledgeRepository = $container->get(PlayerItemKnowledgeEntityRepository::class);
        \assert($knowledgeRepository instanceof PlayerItemKnowledgeEntityRepository);
        $this->knowledgeRepository = $knowledgeRepository;

        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
        $this->itemRepository = null;
        $this->knowledgeRepository = null;
    }

    public function testItemRepositorySupportsTransferAndStatsContracts(): void
    {
        $book901 = $this->createItem(901, ItemTypeEnum::BOOK, null, 'item.book.901.name');
        $book902 = $this->createItem(902, ItemTypeEnum::BOOK, null, 'item.book.902.name');
        $misc903 = $this->createItem(903, ItemTypeEnum::MISC, 1, 'item.misc.903.name');
        $misc904 = $this->createItem(904, ItemTypeEnum::MISC, 2, 'item.misc.904.name');
        $this->attachBookList($book901, 1);
        $this->attachBookList($book902, 2);
        $this->flush();

        $byTypeAndSource = $this->itemRepository()->findByTypeAndSourceIds(ItemTypeEnum::BOOK, [902, 901]);
        self::assertCount(2, $byTypeAndSource);
        self::assertSame([901, 902], array_map(static fn (ItemEntity $item): int => $item->getSourceId(), $byTypeAndSource));

        $byIds = $this->itemRepository()->findByIds([$book901->getId() ?? 0, $misc903->getId() ?? 0]);
        self::assertCount(2, $byIds);

        self::assertSame(4, $this->itemRepository()->countAll());
        self::assertSame(2, $this->itemRepository()->countByType(ItemTypeEnum::BOOK));
        self::assertSame(2, $this->itemRepository()->countByType(ItemTypeEnum::MISC));
        self::assertSame([1 => 1, 2 => 1], $this->itemRepository()->findMiscTotalsByRank());
        self::assertSame([1 => 1, 2 => 1], $this->itemRepository()->findBookTotalsByListNumber());
    }

    public function testKnowledgeRepositorySupportsTransferAndStatsContracts(): void
    {
        $user = $this->createUser('integration-owner@example.com');
        $player = $this->createPlayer($user, 'Main');

        $book901 = $this->createItem(901, ItemTypeEnum::BOOK, null, 'item.book.901.name');
        $book902 = $this->createItem(902, ItemTypeEnum::BOOK, null, 'item.book.902.name');
        $misc903 = $this->createItem(903, ItemTypeEnum::MISC, 1, 'item.misc.903.name');
        $misc904 = $this->createItem(904, ItemTypeEnum::MISC, 2, 'item.misc.904.name');
        $this->attachBookList($book901, 1);
        $this->attachBookList($book901, 4);
        $this->attachBookList($book902, 2);
        $this->flush();

        $this->learn($player, $book901);
        $this->learn($player, $misc903);
        $this->learn($player, $misc904);
        $this->flush();

        self::assertSame(3, $this->knowledgeRepository()->countLearnedByPlayer($player));
        self::assertSame(1, $this->knowledgeRepository()->countLearnedByPlayerAndType($player, ItemTypeEnum::BOOK));
        self::assertSame(2, $this->knowledgeRepository()->countLearnedByPlayerAndType($player, ItemTypeEnum::MISC));
        self::assertSame([1 => 1, 2 => 1], $this->knowledgeRepository()->findLearnedMiscCountsByRank($player));
        self::assertSame([1 => 1, 4 => 1], $this->knowledgeRepository()->findLearnedBookCountsByListNumber($player));

        $book901Id = $book901->getId();
        self::assertNotNull($book901Id);
        $deleted = $this->knowledgeRepository()->deleteByPlayerAndItemIds($player, [$book901Id]);
        self::assertSame(1, $deleted);
        self::assertSame(2, $this->knowledgeRepository()->countLearnedByPlayer($player));
    }

    private function truncateTables(): void
    {
        $this->entityManager()?->getConnection()->executeStatement('TRUNCATE TABLE player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function createUser(string $email): UserEntity
    {
        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS');

        $this->entityManager()?->persist($user);
        $this->flush();

        return $user;
    }

    private function createPlayer(UserEntity $user, string $name): PlayerEntity
    {
        $player = new PlayerEntity()
            ->setUser($user)
            ->setName($name);

        $this->entityManager()?->persist($player);
        $this->flush();

        return $player;
    }

    private function createItem(int $sourceId, ItemTypeEnum $type, ?int $rank, string $nameKey): ItemEntity
    {
        $item = new ItemEntity()
            ->setSourceId($sourceId)
            ->setType($type)
            ->setRank($rank)
            ->setNameKey($nameKey);

        $this->entityManager()?->persist($item);
        $this->flush();

        return $item;
    }

    private function attachBookList(ItemEntity $item, int $listNumber): void
    {
        $this->entityManager()?->persist(new ItemBookListEntity()
            ->setItem($item)
            ->setListNumber($listNumber)
            ->setIsSpecialList(0 === $listNumber % 4));
    }

    private function learn(PlayerEntity $player, ItemEntity $item): void
    {
        $this->entityManager()?->persist(new PlayerItemKnowledgeEntity()
            ->setPlayer($player)
            ->setItem($item)
            ->setLearnedAt(new DateTimeImmutable()));
    }

    private function flush(): void
    {
        $this->entityManager()?->flush();
    }

    private function entityManager(): ?EntityManagerInterface
    {
        return $this->entityManager;
    }

    private function itemRepository(): ItemEntityRepository
    {
        if (null === $this->itemRepository) {
            throw new LogicException('Item repository is not initialized.');
        }

        return $this->itemRepository;
    }

    private function knowledgeRepository(): PlayerItemKnowledgeEntityRepository
    {
        if (null === $this->knowledgeRepository) {
            throw new LogicException('Knowledge repository is not initialized.');
        }

        return $this->knowledgeRepository;
    }
}
