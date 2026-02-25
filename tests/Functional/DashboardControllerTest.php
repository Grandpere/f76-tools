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

namespace App\Tests\Functional;

use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
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

    public function testDashboardRedirectsWhenNotAuthenticated(): void
    {
        $this->browser()->request('GET', '/');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testDashboardRendersCatalogDataForAuthenticatedUser(): void
    {
        $user = $this->createUser('dashboard@example.com');
        $this->createPlayer($user, 'Alpha');
        $this->createPlayer($user, 'Bravo');

        $this->browser()->loginUser($user);
        $crawler = $this->browser()->request('GET', '/');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('[data-controller="item-catalog"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-players-url-value="/api/players"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-players-base-url-value="/api/players"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-initial-player-id-value="1"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-storage-key-value="f76:item-catalog:ui:1"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-target="statsPanel"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-target="exportButton"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-target="importFileInput"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-target="importMergeCheckbox"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-target="importButton"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-target="importUnknownPanel"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-target="miscList"]'));
        self::assertCount(1, $crawler->filter('[data-item-catalog-target="bookList"]'));
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
}
