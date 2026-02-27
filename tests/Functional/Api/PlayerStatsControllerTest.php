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

namespace App\Tests\Functional\Api;

use App\Catalog\Domain\Entity\ItemBookListEntity;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Domain\Entity\PlayerEntity;
use App\Progression\Domain\Entity\PlayerItemKnowledgeEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PlayerStatsControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?KernelBrowser $client = null;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $entityManager = $this->client->getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
        $this->client = null;
    }

    public function testStatsReturnsExpectedCounters(): void
    {
        $user = $this->createUser('stats-owner@example.com');
        $player = $this->createPlayer($user, 'Main');

        $miscRank1A = $this->createItem(701, ItemTypeEnum::MISC, 1, 'item.misc.701.name');
        $miscRank1B = $this->createItem(702, ItemTypeEnum::MISC, 1, 'item.misc.702.name');
        $miscRank2 = $this->createItem(703, ItemTypeEnum::MISC, 2, 'item.misc.703.name');
        $bookList1 = $this->createBookItem(801, 'item.book.801.name', [1]);
        $bookList1And4 = $this->createBookItem(802, 'item.book.802.name', [1, 4]);
        $bookList2 = $this->createBookItem(803, 'item.book.803.name', [2]);

        $this->learn($player, $miscRank1A);
        $this->learn($player, $miscRank2);
        $this->learn($player, $bookList1And4);

        $this->browser()->loginUser($user);
        $this->browser()->request('GET', sprintf('/api/players/%s/stats', $player->getPublicId()));

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $payload = $this->decodeArray($this->browser()->getResponse()->getContent() ?: '{}');

        $overall = $this->readMap($payload, 'overall');
        self::assertSame(6, $this->readInt($overall, 'total'));
        self::assertSame(3, $this->readInt($overall, 'learned'));
        self::assertSame(50, $this->readInt($overall, 'percent'));

        $byType = $this->readMap($payload, 'byType');
        $misc = $this->readMap($byType, 'misc');
        $book = $this->readMap($byType, 'book');
        self::assertSame(3, $this->readInt($misc, 'total'));
        self::assertSame(2, $this->readInt($misc, 'learned'));
        self::assertSame(67, $this->readInt($misc, 'percent'));
        self::assertSame(3, $this->readInt($book, 'total'));
        self::assertSame(1, $this->readInt($book, 'learned'));
        self::assertSame(33, $this->readInt($book, 'percent'));

        $miscByRank = $this->readList($payload, 'miscByRank');
        $rank1 = $this->findByKeyValue($miscByRank, 'rank', 1);
        $rank2 = $this->findByKeyValue($miscByRank, 'rank', 2);
        self::assertSame(2, $this->readInt($rank1, 'total'));
        self::assertSame(1, $this->readInt($rank1, 'learned'));
        self::assertSame(50, $this->readInt($rank1, 'percent'));
        self::assertSame(1, $this->readInt($rank2, 'total'));
        self::assertSame(1, $this->readInt($rank2, 'learned'));
        self::assertSame(100, $this->readInt($rank2, 'percent'));

        $bookByList = $this->readList($payload, 'bookByList');
        $list1 = $this->findByKeyValue($bookByList, 'listNumber', 1);
        $list2 = $this->findByKeyValue($bookByList, 'listNumber', 2);
        $list4 = $this->findByKeyValue($bookByList, 'listNumber', 4);
        self::assertSame(2, $this->readInt($list1, 'total'));
        self::assertSame(1, $this->readInt($list1, 'learned'));
        self::assertSame(50, $this->readInt($list1, 'percent'));
        self::assertSame(1, $this->readInt($list2, 'total'));
        self::assertSame(0, $this->readInt($list2, 'learned'));
        self::assertSame(0, $this->readInt($list2, 'percent'));
        self::assertSame(1, $this->readInt($list4, 'total'));
        self::assertSame(1, $this->readInt($list4, 'learned'));
        self::assertSame(100, $this->readInt($list4, 'percent'));
    }

    public function testStatsReturns404ForForeignPlayer(): void
    {
        $owner = $this->createUser('stats-owner2@example.com');
        $other = $this->createUser('stats-other@example.com');
        $player = $this->createPlayer($owner, 'Owner');

        $this->browser()->loginUser($other);
        $this->browser()->request('GET', sprintf('/api/players/%s/stats', $player->getPublicId()));

        self::assertSame(404, $this->browser()->getResponse()->getStatusCode());
    }

    private function createUser(string $email): UserEntity
    {
        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS');

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function createPlayer(UserEntity $user, string $name): PlayerEntity
    {
        $player = new PlayerEntity()
            ->setUser($user)
            ->setName($name);

        $this->entityManager?->persist($player);
        $this->entityManager?->flush();

        return $player;
    }

    private function createItem(int $sourceId, ItemTypeEnum $type, ?int $rank, string $nameKey): ItemEntity
    {
        $item = new ItemEntity()
            ->setSourceId($sourceId)
            ->setType($type)
            ->setRank($rank)
            ->setNameKey($nameKey);

        $this->entityManager?->persist($item);
        $this->entityManager?->flush();

        return $item;
    }

    /**
     * @param list<int> $listNumbers
     */
    private function createBookItem(int $sourceId, string $nameKey, array $listNumbers): ItemEntity
    {
        $item = $this->createItem($sourceId, ItemTypeEnum::BOOK, null, $nameKey);
        foreach ($listNumbers as $listNumber) {
            $this->entityManager?->persist(new ItemBookListEntity()
                ->setItem($item)
                ->setListNumber($listNumber)
                ->setIsSpecialList(0 === $listNumber % 4));
        }
        $this->entityManager?->flush();

        return $item;
    }

    private function learn(PlayerEntity $player, ItemEntity $item): void
    {
        $this->entityManager?->persist(new PlayerItemKnowledgeEntity()
            ->setPlayer($player)
            ->setItem($item)
            ->setLearnedAt(new DateTimeImmutable()));
        $this->entityManager?->flush();
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArray(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            self::fail('Invalid JSON object.');
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        self::fail(sprintf('Expected int for key "%s".', $key));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function readMap(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            self::fail(sprintf('Expected map for key "%s".', $key));
        }

        $normalized = [];
        foreach ($value as $valueKey => $valueValue) {
            $normalized[(string) $valueKey] = $valueValue;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, mixed>
     */
    private function readList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            self::fail(sprintf('Expected list for key "%s".', $key));
        }

        return array_values($value);
    }

    /**
     * @param array<int, mixed> $rows
     *
     * @return array<string, mixed>
     */
    private function findByKeyValue(array $rows, string $key, int $expected): array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = $row[$key] ?? null;
            if ((is_int($value) || is_numeric($value)) && (int) $value === $expected) {
                $normalized = [];
                foreach ($row as $rowKey => $rowValue) {
                    $normalized[(string) $rowKey] = $rowValue;
                }

                return $normalized;
            }
        }

        self::fail(sprintf('Row not found for %s=%d.', $key, $expected));
    }
}
