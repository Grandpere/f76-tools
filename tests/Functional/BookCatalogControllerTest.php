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
use App\Progression\Domain\Entity\PlayerEntity;
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
        $this->createPlayer($user, 'Main dweller');
        $this->createBook(321, 'catalog.book.front.alpha', 'Plan: Alpha Receiver', [
            'daily_ops' => true,
            'expeditions' => true,
            'enemies' => true,
            'seasonal_content' => true,
            'world_spawns' => true,
            'obtained' => [
                'text' => 'Samuel or Minerva',
                'icons' => ['Samuel (Wastelanders)', 'Minerva'],
            ],
        ]);
        $this->createBook(654, 'catalog.book.front.bravo', 'Recipe: Bravo Soup');
        $this->createBook(777, 'catalog.book.front.charlie', 'Plan: Charlie Receiver', [
            'obtained' => 'Bullion vendors',
            'purchase_currency' => 'gold_bullion',
        ]);

        $this->browser()->loginUser($user);
        $crawler = $this->browser()->request('GET', '/plans-recipes?q=alpha');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('.catalog-books-grid'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('.catalog-book-card')->count());
        self::assertStringContainsString('catalog.book.front.alpha', (string) $this->browser()->getResponse()->getContent());
        self::assertSame('get', $crawler->filter('form.catalog-toolbar')->attr('method'));
        self::assertStringEndsWith('/en/plans-recipes', (string) $crawler->filter('form.catalog-toolbar')->attr('action'));
        self::assertSame('alpha', (string) $crawler->filter('input[name="q"]')->attr('value'));
        self::assertSame('1', (string) $crawler->filter('input[name="page"]')->attr('value'));
        self::assertCount(1, $crawler->filter('input[name="player"]'));
        self::assertSame('all', (string) $crawler->filter('input[name="knowledge"]:checked')->attr('value'));
        self::assertCount(1, $crawler->filter('select[name="sort"]'));
        self::assertStringContainsString('<option value="name_asc" selected>', (string) $this->browser()->getResponse()->getContent());
        self::assertCount(1, $crawler->filter('[data-controller*="book-catalog-knowledge"]'));
        self::assertCount(1, $crawler->filter('[data-book-catalog-knowledge-target="playerSelect"]'));
        self::assertCount(1, $crawler->filter('[data-book-catalog-knowledge-target="playerInput"]'));
        self::assertCount(1, $crawler->filter('[data-book-catalog-knowledge-target="results"]'));
        self::assertCount(1, $crawler->filter('.item-learned-checkbox[data-book-checkbox="1"]'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('input[name="kinds[]"]')->count());
        self::assertGreaterThanOrEqual(1, $crawler->filter('input[name="categories[]"]')->count());
        self::assertGreaterThanOrEqual(1, $crawler->filter('input[name="vendorFilters[]"]')->count());
        self::assertStringContainsString('vendorFilters[]', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('vendor_minerva', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('Gold bullion vendors', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('Unlock test', (string) $this->browser()->getResponse()->getContent());
        self::assertCount(1, $crawler->filter('[data-catalog-filters-target="summary"]'));
        self::assertCount(1, $crawler->filter('[data-catalog-filters-target="results"]'));
        self::assertStringContainsString('0 / 1 learned', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('Icon legend', (string) $this->browser()->getResponse()->getContent());
        self::assertGreaterThanOrEqual(1, $crawler->filter('input[name="lists[]"]')->count());
        self::assertSame('Plans & Recipes', trim($crawler->filter('.app-primary-nav-link.is-active')->text()));
        self::assertStringContainsString('/assets/icons/FO76_dailyops_uplink.png', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('/assets/icons/FO76_ui_workshopraid_team.png', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('/assets/icons/FO76_collections_stamps01.webp', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('/assets/icons/FO76_scoresprite_seasons.png', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('/assets/icons/FO76_ui_exploration_team.png', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('/assets/icons/FO76_Plan_equipment.webp', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('/assets/icons/FO76_recipe.webp', (string) $this->browser()->getResponse()->getContent());
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

    /**
     * @param array<string, mixed> $providerBExtra
     */
    private function createBook(int $sourceId, string $nameKey, string $displayName, array $providerBExtra = []): void
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
        $item->upsertExternalSource('fallout_wiki', sprintf('%08X', $sourceId), 'https://example.test/wiki/'.$sourceId, array_merge([
            'name' => $displayName,
            'name_en' => $displayName,
            'source_page' => 'Fallout_76_Weapon_Plans',
            'unlocks' => ['text' => 'Unlock test'],
        ], $providerBExtra));
        $item->addBookList(4, false);

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
