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

use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Domain\Entity\PlayerEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProgressionControllerTest extends WebTestCase
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

    public function testPageRedirectsWhenNotAuthenticated(): void
    {
        $this->browser()->request('GET', '/');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testPageRendersProgressionForAuthenticatedUser(): void
    {
        $user = $this->createUser('progression@example.com');
        $alpha = $this->createPlayer($user, 'Alpha');
        $this->createPlayer($user, 'Bravo');

        $this->browser()->loginUser($user);
        $crawler = $this->browser()->request('GET', '/');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('[data-controller="player-progression"]'));
        self::assertCount(1, $crawler->filter('[data-player-progression-players-url-value="/api/players"]'));
        self::assertCount(1, $crawler->filter('[data-player-progression-players-base-url-value="/api/players"]'));
        self::assertCount(1, $crawler->filter(sprintf('[data-player-progression-initial-player-id-value="%s"]', $alpha->getPublicId())));
        self::assertCount(1, $crawler->filter('[data-player-progression-target="statsPanel"]'));
        self::assertStringContainsString('plans-recipes', (string) $this->browser()->getResponse()->getContent());
        self::assertCount(1, $crawler->filter('.app-primary-nav-link.is-active'));
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

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE minerva_rotation, contact_message, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
