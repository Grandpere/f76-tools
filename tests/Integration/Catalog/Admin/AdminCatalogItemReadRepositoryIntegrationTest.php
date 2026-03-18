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

namespace App\Tests\Integration\Catalog\Admin;

use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Catalog\Infrastructure\Persistence\ItemEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminCatalogItemReadRepositoryIntegrationTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?ItemEntityRepository $itemRepository = null;

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

        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
        $this->itemRepository = null;
    }

    public function testAdminQueryCanSearchByProviderAndExactSourceId(): void
    {
        $book = $this->createItem(2853828, ItemTypeEnum::BOOK, 'item.book.2853828.name');
        $book->upsertExternalSource('fandom', '002B8BC4', 'https://fallout.fandom.com/wiki/Recipe:_Healing_salve_(Toxic_Valley)', [
            'name_en' => 'Recipe: Healing Salve (Toxic Valley)',
        ]);

        $misc = $this->createItem(10, ItemTypeEnum::MISC, 'item.misc.10.name', 1);
        $misc->upsertExternalSource('nukaknights', 'legendary_mod_10', null, [
            'name_en' => 'Arms Keeper',
        ]);
        $this->flush();

        self::assertSame(1, $this->itemRepository()->countByAdminQuery(null, 'fandom'));
        self::assertSame(1, $this->itemRepository()->countByAdminQuery(null, '2853828'));
        self::assertSame(1, $this->itemRepository()->countByAdminQuery(ItemTypeEnum::BOOK, '002b8bc4'));

        $rows = $this->itemRepository()->findByAdminQuery(ItemTypeEnum::BOOK, 'fandom', 1, 20);
        self::assertCount(1, $rows);
        self::assertSame($book->getPublicId(), $rows[0]->getPublicId());
    }

    public function testFindOneDetailedByPublicIdLoadsExternalSourcesAndBookLists(): void
    {
        $item = $this->createItem(2161, ItemTypeEnum::BOOK, 'item.book.2161.name');
        $item->upsertExternalSource('fandom', '00000871', 'https://fallout.fandom.com/wiki/Plan:_Assault_rifle_fierce_receiver', [
            'name_en' => 'Plan: Assault Rifle Fierce Receiver',
        ]);
        $item->upsertExternalSource('fallout_wiki', '00000871', 'https://fallout.wiki/wiki/Plan:_Assault_Rifle_Fierce_Receiver', [
            'name_en' => 'Plan: Assault Rifle Fierce Receiver',
        ]);
        $item->addBookList(4, true);
        $this->flush();

        $detailed = $this->itemRepository()->findOneDetailedByPublicId($item->getPublicId());

        self::assertInstanceOf(ItemEntity::class, $detailed);
        self::assertCount(2, $detailed->getExternalSources());
        self::assertCount(1, $detailed->getBookLists());
        self::assertSame('fandom', $detailed->findExternalSourceByProvider('fandom')?->getProvider());
    }

    public function testFindAllDetailedByAdminQueryLoadsExternalSourcesForAllMatchingItems(): void
    {
        $first = $this->createItem(1001, ItemTypeEnum::BOOK, 'item.book.1001.name');
        $first->upsertExternalSource('fandom', 'F1001', null, [
            'name_en' => 'Alpha source',
        ]);

        $second = $this->createItem(1002, ItemTypeEnum::BOOK, 'item.book.1002.name');
        $second->upsertExternalSource('fallout_wiki', 'W1002', null, [
            'name_en' => 'Beta source',
        ]);

        $misc = $this->createItem(2001, ItemTypeEnum::MISC, 'item.misc.2001.name', 1);
        $misc->upsertExternalSource('nukaknights', 'N2001', null, [
            'name_en' => 'Gamma source',
        ]);
        $this->flush();

        $rows = $this->itemRepository()->findAllDetailedByAdminQuery(ItemTypeEnum::BOOK, null);

        self::assertCount(2, $rows);
        self::assertSame($first->getPublicId(), $rows[0]->getPublicId());
        self::assertSame($second->getPublicId(), $rows[1]->getPublicId());
        self::assertCount(1, $rows[0]->getExternalSources());
        self::assertCount(1, $rows[1]->getExternalSources());
    }

    private function truncateTables(): void
    {
        $this->entityManager()?->getConnection()->executeStatement('TRUNCATE TABLE item_book_list, item_external_source, item RESTART IDENTITY CASCADE');
    }

    private function createItem(int $sourceId, ItemTypeEnum $type, string $nameKey, ?int $rank = null): ItemEntity
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
}
