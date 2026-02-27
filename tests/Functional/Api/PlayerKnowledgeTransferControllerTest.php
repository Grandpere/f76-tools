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

use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Domain\Entity\PlayerEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PlayerKnowledgeTransferControllerTest extends WebTestCase
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

    public function testExportReturnsLearnedItemsAsTypeAndSourceId(): void
    {
        $user = $this->createUser('transfer-export@example.com');
        $player = $this->createPlayer($user, 'Main');
        $book = $this->createItem(901, ItemTypeEnum::BOOK, null, 'item.book.901.name');
        $misc = $this->createItem(902, ItemTypeEnum::MISC, 1, 'item.misc.902.name');

        $this->browser()->loginUser($user);
        $this->browser()->request('PUT', sprintf('/api/players/%s/items/%s/learned', $player->getPublicId(), $book->getPublicId()));
        $this->browser()->request('PUT', sprintf('/api/players/%s/items/%s/learned', $player->getPublicId(), $misc->getPublicId()));

        $this->browser()->request('GET', sprintf('/api/players/%s/knowledge/export', $player->getPublicId()));
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $payload = $this->decodeMap($this->browser()->getResponse()->getContent() ?: '{}');
        $learnedItems = $this->readList($payload, 'learnedItems');

        self::assertCount(2, $learnedItems);
        self::assertTrue($this->containsEntry($learnedItems, 'BOOK', 901));
        self::assertTrue($this->containsEntry($learnedItems, 'MISC', 902));
    }

    public function testImportReplaceOverridesLearnedSet(): void
    {
        $user = $this->createUser('transfer-import-replace@example.com');
        $player = $this->createPlayer($user, 'Main');
        $bookA = $this->createItem(911, ItemTypeEnum::BOOK, null, 'item.book.911.name');
        $bookB = $this->createItem(912, ItemTypeEnum::BOOK, null, 'item.book.912.name');

        $this->browser()->loginUser($user);
        $this->browser()->request('PUT', sprintf('/api/players/%s/items/%s/learned', $player->getPublicId(), $bookA->getPublicId()));
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->jsonRequest('POST', sprintf('/api/players/%s/knowledge/import', $player->getPublicId()), [
            'learnedItems' => [
                ['type' => 'BOOK', 'sourceId' => 912],
            ],
        ]);
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->request('GET', sprintf('/api/players/%s/items?type=BOOK', $player->getPublicId()));
        $rows = $this->decodeList($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertSame([
            911 => false,
            912 => true,
        ], $this->mapLearnedBySourceId($rows));
    }

    public function testImportMergeAddsWithoutRemoving(): void
    {
        $user = $this->createUser('transfer-import-merge@example.com');
        $player = $this->createPlayer($user, 'Main');
        $miscA = $this->createItem(921, ItemTypeEnum::MISC, 1, 'item.misc.921.name');
        $miscB = $this->createItem(922, ItemTypeEnum::MISC, 1, 'item.misc.922.name');

        $this->browser()->loginUser($user);
        $this->browser()->request('PUT', sprintf('/api/players/%s/items/%s/learned', $player->getPublicId(), $miscA->getPublicId()));
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->jsonRequest('POST', sprintf('/api/players/%s/knowledge/import', $player->getPublicId()), [
            'replace' => false,
            'learnedItems' => [
                ['type' => 'MISC', 'sourceId' => 922],
            ],
        ]);
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->request('GET', sprintf('/api/players/%s/items?type=MISC', $player->getPublicId()));
        $rows = $this->decodeList($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertSame([
            921 => true,
            922 => true,
        ], $this->mapLearnedBySourceId($rows));
    }

    public function testPreviewImportReturnsDiffAndUnknownItems(): void
    {
        $user = $this->createUser('transfer-preview@example.com');
        $player = $this->createPlayer($user, 'Main');
        $bookKnown = $this->createItem(931, ItemTypeEnum::BOOK, null, 'item.book.931.name');
        $this->browser()->loginUser($user);
        $this->browser()->request('PUT', sprintf('/api/players/%s/items/%s/learned', $player->getPublicId(), $bookKnown->getPublicId()));
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->jsonRequest('POST', sprintf('/api/players/%s/knowledge/preview-import', $player->getPublicId()), [
            'version' => 1,
            'replace' => true,
            'learnedItems' => [
                ['type' => 'BOOK', 'sourceId' => 999999], // unknown
            ],
        ]);
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $payload = $this->decodeMap($this->browser()->getResponse()->getContent() ?: '{}');

        self::assertSame(0, $this->readIntValue($payload, 'wouldAdd'));
        self::assertSame(1, $this->readIntValue($payload, 'wouldRemove'));
        $unknown = $this->readList($payload, 'unknownItems');
        self::assertCount(1, $unknown);
        self::assertTrue($this->containsEntry($unknown, 'BOOK', 999999));
    }

    public function testPreviewImportDoesNotMutateKnowledgeState(): void
    {
        $user = $this->createUser('transfer-preview-no-mutation@example.com');
        $player = $this->createPlayer($user, 'Main');
        $known = $this->createItem(941, ItemTypeEnum::BOOK, null, 'item.book.941.name');
        $new = $this->createItem(942, ItemTypeEnum::BOOK, null, 'item.book.942.name');

        $this->browser()->loginUser($user);
        $this->browser()->request('PUT', sprintf('/api/players/%s/items/%s/learned', $player->getPublicId(), $known->getPublicId()));
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->jsonRequest('POST', sprintf('/api/players/%s/knowledge/preview-import', $player->getPublicId()), [
            'replace' => false,
            'learnedItems' => [
                ['type' => 'BOOK', 'sourceId' => 942],
            ],
        ]);
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->request('GET', sprintf('/api/players/%s/items?type=BOOK', $player->getPublicId()));
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $rows = $this->decodeList($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertSame([
            941 => true,
            942 => false,
        ], $this->mapLearnedBySourceId($rows));
    }

    public function testImportRejectsUnsupportedVersion(): void
    {
        $user = $this->createUser('transfer-version@example.com');
        $player = $this->createPlayer($user, 'Main');
        $this->browser()->loginUser($user);

        $this->browser()->jsonRequest('POST', sprintf('/api/players/%s/knowledge/import', $player->getPublicId()), [
            'version' => 2,
            'learnedItems' => [],
        ]);
        self::assertSame(400, $this->browser()->getResponse()->getStatusCode());
    }

    public function testCannotExportOrImportForeignPlayer(): void
    {
        $owner = $this->createUser('transfer-owner@example.com');
        $other = $this->createUser('transfer-other@example.com');
        $player = $this->createPlayer($owner, 'Owner');

        $this->browser()->loginUser($other);
        $this->browser()->request('GET', sprintf('/api/players/%s/knowledge/export', $player->getPublicId()));
        self::assertSame(404, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->jsonRequest('POST', sprintf('/api/players/%s/knowledge/import', $player->getPublicId()), [
            'learnedItems' => [],
        ]);
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
    private function decodeMap(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            self::fail('Invalid JSON map.');
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeList(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            self::fail('Invalid JSON list.');
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = [];
            foreach ($row as $key => $value) {
                $normalized[(string) $key] = $value;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function readList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (!is_array($value)) {
            self::fail(sprintf('Expected list for key "%s".', $key));
        }

        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = [];
            foreach ($row as $entryKey => $entryValue) {
                $normalized[(string) $entryKey] = $entryValue;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function containsEntry(array $rows, string $type, int $sourceId): bool
    {
        foreach ($rows as $row) {
            $rowType = $row['type'] ?? null;
            $rowSourceId = $row['sourceId'] ?? null;
            if (is_string($rowType) && (is_int($rowSourceId) || is_numeric($rowSourceId))
                && strtoupper($rowType) === $type
                && (int) $rowSourceId === $sourceId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readIntValue(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        self::fail(sprintf('Expected int for key "%s".', $key));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<int, bool>
     */
    private function mapLearnedBySourceId(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $sourceId = $row['sourceId'] ?? null;
            $learned = $row['learned'] ?? null;
            if ((is_int($sourceId) || is_numeric($sourceId)) && is_bool($learned)) {
                $map[(int) $sourceId] = $learned;
            }
        }
        ksort($map);

        return $map;
    }
}
