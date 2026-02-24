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

use App\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PlayerControllerTest extends WebTestCase
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
        $client = $this->browser();
        $client->request('GET', '/api/players');

        self::assertSame(302, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('location'));
    }

    public function testAuthenticatedUserCanCreateAndListPlayers(): void
    {
        $client = $this->browser();
        $user = $this->createUser('user1@example.com');
        $client->loginUser($user);

        $client->jsonRequest('POST', '/api/players', ['name' => 'Main Character']);
        self::assertSame(201, $client->getResponse()->getStatusCode());
        $created = $this->decodeArrayResponse($client->getResponse()->getContent() ?: '[]');
        self::assertSame('Main Character', $created['name'] ?? null);

        $client->request('GET', '/api/players');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $payload = $this->decodeListResponse($client->getResponse()->getContent() ?: '[]');
        self::assertCount(1, $payload);
        self::assertSame('Main Character', $payload[0]['name'] ?? null);
    }

    public function testUserCannotAccessAnotherUsersPlayer(): void
    {
        $client = $this->browser();
        $owner = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');

        $client->loginUser($owner);
        $client->jsonRequest('POST', '/api/players', ['name' => 'Owner Player']);
        $created = $this->decodeArrayResponse($client->getResponse()->getContent() ?: '[]');
        $playerId = $this->readInt($created, 'id');
        self::assertGreaterThan(0, $playerId);

        $client->loginUser($other);
        $client->request('GET', sprintf('/api/players/%d', $playerId));
        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testUserCanUpdateAndDeleteOwnPlayer(): void
    {
        $client = $this->browser();
        $user = $this->createUser('owner2@example.com');
        $client->loginUser($user);

        $client->jsonRequest('POST', '/api/players', ['name' => 'Old Name']);
        $created = $this->decodeArrayResponse($client->getResponse()->getContent() ?: '[]');
        $playerId = $this->readInt($created, 'id');
        self::assertGreaterThan(0, $playerId);

        $client->jsonRequest('PATCH', sprintf('/api/players/%d', $playerId), ['name' => 'New Name']);
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $updated = $this->decodeArrayResponse($client->getResponse()->getContent() ?: '[]');
        self::assertSame('New Name', $updated['name'] ?? null);

        $client->request('DELETE', sprintf('/api/players/%d', $playerId));
        self::assertSame(204, $client->getResponse()->getStatusCode());

        $client->request('GET', sprintf('/api/players/%d', $playerId));
        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testCreateReturnsConflictForDuplicateName(): void
    {
        $client = $this->browser();
        $user = $this->createUser('duplicate-create@example.com');
        $client->loginUser($user);

        $client->jsonRequest('POST', '/api/players', ['name' => 'Duplicate Name']);
        self::assertSame(201, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/players', ['name' => 'Duplicate Name']);
        self::assertSame(409, $client->getResponse()->getStatusCode());
        $payload = $this->decodeArrayResponse($client->getResponse()->getContent() ?: '{}');
        self::assertSame('Player name already exists.', $payload['error'] ?? null);
    }

    public function testUpdateReturnsConflictForDuplicateName(): void
    {
        $client = $this->browser();
        $user = $this->createUser('duplicate-update@example.com');
        $client->loginUser($user);

        $client->jsonRequest('POST', '/api/players', ['name' => 'Alpha']);
        $first = $this->decodeArrayResponse($client->getResponse()->getContent() ?: '{}');
        $firstId = $this->readInt($first, 'id');

        $client->jsonRequest('POST', '/api/players', ['name' => 'Bravo']);
        $second = $this->decodeArrayResponse($client->getResponse()->getContent() ?: '{}');
        $secondId = $this->readInt($second, 'id');

        $client->jsonRequest('PATCH', sprintf('/api/players/%d', $secondId), ['name' => 'Alpha']);
        self::assertSame(409, $client->getResponse()->getStatusCode());
        $payload = $this->decodeArrayResponse($client->getResponse()->getContent() ?: '{}');
        self::assertSame('Player name already exists.', $payload['error'] ?? null);

        $client->request('GET', sprintf('/api/players/%d', $firstId));
        self::assertSame(200, $client->getResponse()->getStatusCode());
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
    private function readInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) && !is_numeric($value)) {
            return 0;
        }

        return (int) $value;
    }
}
