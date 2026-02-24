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

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PlayerItemKnowledgeControllerTest extends WebTestCase
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

    public function testUnauthenticatedAccessIsDenied(): void
    {
        $owner = $this->createUser('owner-auth@example.com');
        $player = $this->createPlayer($owner, 'Owner');

        $this->browser()->request('GET', sprintf('/api/players/%d/items', $player->getId()));

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testCanLearnAndUnlearnItem(): void
    {
        $user = $this->createUser('owner-knowledge@example.com');
        $player = $this->createPlayer($user, 'Main');
        $book = $this->createItem(201, ItemTypeEnum::BOOK, null, 'item.book.201.name');
        $this->createItem(301, ItemTypeEnum::MISC, 1, 'item.misc.301.name');

        $this->browser()->loginUser($user);

        $this->browser()->request('GET', sprintf('/api/players/%d/items?type=BOOK', $player->getId()));
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $before = $this->decodeListResponse($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertCount(1, $before);
        self::assertFalse($this->readBool($before[0] ?? [], 'learned'));

        $this->browser()->request('PUT', sprintf('/api/players/%d/items/%d/learned', $player->getId(), $book->getId()));
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $setPayload = $this->decodeArrayResponse($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertTrue($this->readBool($setPayload, 'learned'));

        $this->browser()->request('GET', sprintf('/api/players/%d/items?type=BOOK', $player->getId()));
        $afterLearn = $this->decodeListResponse($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertTrue($this->readBool($afterLearn[0] ?? [], 'learned'));

        $this->browser()->request('DELETE', sprintf('/api/players/%d/items/%d/learned', $player->getId(), $book->getId()));
        self::assertSame(204, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->request('GET', sprintf('/api/players/%d/items?type=BOOK', $player->getId()));
        $afterUnlearn = $this->decodeListResponse($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertFalse($this->readBool($afterUnlearn[0] ?? [], 'learned'));
    }

    public function testCannotManageKnowledgeForAnotherUsersPlayer(): void
    {
        $owner = $this->createUser('owner-cross@example.com');
        $other = $this->createUser('other-cross@example.com');
        $player = $this->createPlayer($owner, 'Owner');
        $book = $this->createItem(202, ItemTypeEnum::BOOK, null, 'item.book.202.name');

        $this->browser()->loginUser($other);
        $this->browser()->request('PUT', sprintf('/api/players/%d/items/%d/learned', $player->getId(), $book->getId()));

        self::assertSame(404, $this->browser()->getResponse()->getStatusCode());
    }

    public function testInvalidTypeFilterReturnsBadRequest(): void
    {
        $owner = $this->createUser('owner-type@example.com');
        $player = $this->createPlayer($owner, 'Owner');

        $this->browser()->loginUser($owner);
        $this->browser()->request('GET', sprintf('/api/players/%d/items?type=INVALID', $player->getId()));

        self::assertSame(400, $this->browser()->getResponse()->getStatusCode());
    }

    public function testSearchQueryFiltersItems(): void
    {
        $owner = $this->createUser('owner-search@example.com');
        $player = $this->createPlayer($owner, 'Owner');
        $this->createItem(410, ItemTypeEnum::BOOK, null, 'catalog.alpha.name');
        $this->createItem(411, ItemTypeEnum::BOOK, null, 'catalog.beta.name');

        $this->browser()->loginUser($owner);
        $this->browser()->request('GET', sprintf('/api/players/%d/items?type=BOOK&q=alpha', $player->getId()));

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $payload = $this->decodeListResponse($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertCount(1, $payload);
        $nameKey = $payload[0]['nameKey'] ?? null;
        self::assertIsString($nameKey);
        self::assertSame('catalog.alpha.name', $nameKey);
    }

    public function testNewFlagIsReturnedWhenPayloadContainsNewOne(): void
    {
        $owner = $this->createUser('owner-new-flag@example.com');
        $player = $this->createPlayer($owner, 'Owner');
        $this->createItem(501, ItemTypeEnum::BOOK, null, 'catalog.new.name', ['new' => 1], 250, 188);

        $this->browser()->loginUser($owner);
        $this->browser()->request('GET', sprintf('/api/players/%d/items?q=catalog.new.name', $player->getId()));

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $payload = $this->decodeListResponse($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertCount(1, $payload);
        $first = $payload[0] ?? [];
        self::assertTrue($this->readBool($first, 'isNew'));
        self::assertSame(250, $this->readInt($first, 'price'));
        self::assertSame(188, $this->readInt($first, 'priceMinerva'));

        $this->entityManager?->clear();
        $item = $this->findItemBySourceIdAndType(501, ItemTypeEnum::BOOK);
        self::assertNotNull($item);
        $item->setPayload([
            'new' => 1,
            'drop_raid' => 1,
            'drop_burningssprings' => 1,
            'drop_dailyops' => 1,
            'vendor_regs' => 1,
            'vendor_samuel' => 1,
            'vendor_mortimer' => 0,
            'info' => '<p>Also available at <strong>Samuel</strong>.</p>',
            'drop_sources' => '<img src="/img/x.png" title="Burning Springs" />',
        ]);
        $this->entityManager?->flush();

        $this->browser()->request('GET', sprintf('/api/players/%d/items?q=catalog.new.name', $player->getId()));
        $payload2 = $this->decodeListResponse($this->browser()->getResponse()->getContent() ?: '[]');
        self::assertCount(1, $payload2);
        $second = $payload2[0] ?? [];
        self::assertTrue($this->readBool($second, 'dropRaid'));
        self::assertTrue($this->readBool($second, 'dropBurningSprings'));
        self::assertTrue($this->readBool($second, 'dropDailyOps'));
        self::assertTrue($this->readBool($second, 'vendorRegs'));
        self::assertTrue($this->readBool($second, 'vendorSamuel'));
        self::assertFalse($this->readBool($second, 'vendorMortimer'));
        self::assertSame('<p>Also available at <strong>Samuel</strong>.</p>', $this->readString($second, 'infoHtml'));
        self::assertSame('<img src="/img/x.png" title="Burning Springs" />', $this->readString($second, 'dropSourcesHtml'));
    }

    private function createUser(string $email): UserEntity
    {
        $user = (new UserEntity())
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS');

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function createPlayer(UserEntity $user, string $name): PlayerEntity
    {
        $player = (new PlayerEntity())
            ->setUser($user)
            ->setName($name);

        $this->entityManager?->persist($player);
        $this->entityManager?->flush();

        return $player;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function createItem(int $sourceId, ItemTypeEnum $type, ?int $rank, string $nameKey, ?array $payload = null, ?int $price = null, ?int $priceMinerva = null): ItemEntity
    {
        $item = (new ItemEntity())
            ->setSourceId($sourceId)
            ->setType($type)
            ->setNameKey($nameKey)
            ->setRank($rank)
            ->setPayload($payload)
            ->setPrice($price)
            ->setPriceMinerva($priceMinerva);

        $this->entityManager?->persist($item);
        $this->entityManager?->flush();

        return $item;
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new \LogicException('Client is not initialized.');
        }

        return $this->client;
    }

    private function findItemBySourceIdAndType(int $sourceId, ItemTypeEnum $type): ?ItemEntity
    {
        if (null === $this->entityManager) {
            return null;
        }

        $result = $this->entityManager->getRepository(ItemEntity::class)->findOneBy([
            'sourceId' => $sourceId,
            'type' => $type,
        ]);

        return $result instanceof ItemEntity ? $result : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArrayResponse(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            self::fail('Invalid JSON object response.');
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
    private function decodeListResponse(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            self::fail('Invalid JSON list response.');
        }

        $normalized = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalizedRow = [];
            foreach ($row as $key => $value) {
                $normalizedRow[(string) $key] = $value;
            }
            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readBool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return '1' === $normalized || 'true' === $normalized || 'yes' === $normalized;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) && !is_numeric($value)) {
            return 0;
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readString(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
