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

namespace App\Tests\Functional\Admin;

use App\Catalog\Domain\Entity\ItemBookListEntity;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Identity\Domain\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CatalogItemControllerTest extends WebTestCase
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
        $this->browser()->request('GET', '/en/admin/catalog/items');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testAdminCanInspectCatalogItemSourcesAndMerge(): void
    {
        $admin = $this->createUser('catalog-admin@example.com');
        $item = $this->createCatalogItem();
        $this->browser()->loginUser($admin);

        $crawler = $this->getAndFollowRedirect('/en/admin/catalog/items?type=BOOK&q=fandom&item='.$item->getPublicId());

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('002B8BC4', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('Generic label', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('1 generic label', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('generic_label_confirmed_by_specific_target', (string) $this->browser()->getResponse()->getContent());
        self::assertCount(1, $crawler->filter(sprintf('a[href*="item=%s"]', $item->getPublicId())));
    }

    private function createUser(string $email): UserEntity
    {
        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS');

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function createCatalogItem(): ItemEntity
    {
        $item = new ItemEntity()
            ->setSourceId(2853828)
            ->setType(ItemTypeEnum::BOOK)
            ->setNameKey('item.book.2853828.name');
        $item->upsertExternalSource('fandom', '002B8BC4', 'https://fallout.fandom.com/wiki/Recipe:_Healing_salve_(Toxic_Valley)', [
            'name_en' => 'Recipe: Healing Salve (Toxic Valley)',
        ]);
        $item->upsertExternalSource('fallout_wiki', '002B8BC4', 'https://fallout.wiki/wiki/Recipe:_Healing_Salve_(Toxic_Valley)', [
            'name_en' => 'Recipe: Healing Salve',
        ]);

        $this->entityManager?->persist($item);
        $this->entityManager?->persist(new ItemBookListEntity()
            ->setItem($item)
            ->setListNumber(24)
            ->setIsSpecialList(true));
        $this->entityManager?->flush();

        return $item;
    }

    private function truncateTables(): void
    {
        $this->entityManager?->getConnection()->executeStatement('TRUNCATE TABLE item_book_list, item_external_source, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }

    private function getAndFollowRedirect(string $uri): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $this->browser()->request('GET', $uri);
        if (302 === $this->browser()->getResponse()->getStatusCode()) {
            return $this->browser()->followRedirect();
        }

        return $crawler;
    }
}
