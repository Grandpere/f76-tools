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

use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Identity\Domain\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BookCatalogControllerTest extends WebTestCase
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
        $this->browser()->request('GET', '/plans-recipes');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testPageRendersMergedBookCardsForAuthenticatedUser(): void
    {
        $user = $this->createUser('books@example.com');
        $this->createBook(321, 'catalog.book.front.alpha', 'Plan: Alpha Receiver');
        $this->createBook(654, 'catalog.book.front.bravo', 'Recipe: Bravo Soup');

        $this->browser()->loginUser($user);
        $crawler = $this->browser()->request('GET', '/plans-recipes?q=alpha');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('.catalog-books-grid'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('.catalog-book-card')->count());
        self::assertStringContainsString('catalog.book.front.alpha', (string) $this->browser()->getResponse()->getContent());
        self::assertGreaterThanOrEqual(1, $crawler->filter('select[name="list"] option')->count());
        self::assertSame('Plans & Recipes', trim($crawler->filter('.app-primary-nav-link.is-active')->text()));
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

    private function createBook(int $sourceId, string $nameKey, string $displayName): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId($sourceId)
            ->setNameKey($nameKey);

        $item->upsertExternalSource('fandom', sprintf('%08X', $sourceId), 'https://example.test/fandom/'.$sourceId, [
            'name' => $displayName,
            'name_en' => $displayName,
            'containers' => true,
        ]);
        $item->upsertExternalSource('fallout_wiki', sprintf('%08X', $sourceId), 'https://example.test/wiki/'.$sourceId, [
            'name' => $displayName,
            'name_en' => $displayName,
            'unlocks' => ['text' => 'Unlock test'],
        ]);

        $this->entityManager?->persist($item);
        $this->entityManager?->flush();
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE minerva_rotation, contact_message, player_item_knowledge, item_external_source, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
