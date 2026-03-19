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

namespace App\Tests\Unit\Catalog\Application\Import;

use App\Catalog\Application\Import\ItemImportApplicationService;
use App\Catalog\Application\Import\ItemImportContextApplier;
use App\Catalog\Application\Import\ItemImportExternalMetadataEnricher;
use App\Catalog\Application\Import\ItemImportExternalUrlResolver;
use App\Catalog\Application\Import\ItemImportFileContextResolver;
use App\Catalog\Application\Import\ItemImportItemHydrator;
use App\Catalog\Application\Import\ItemImportItemRepository;
use App\Catalog\Application\Import\ItemImportPersistence;
use App\Catalog\Application\Import\ItemImportTranslationCatalogBuilder;
use App\Catalog\Application\Import\ItemImportValueNormalizer;
use App\Catalog\Application\Translation\TranslationCatalogReader;
use App\Catalog\Application\Translation\TranslationCatalogWriter;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Infrastructure\Import\FilesystemItemImportSourceReader;
use App\Catalog\Infrastructure\Translation\TranslationCatalogReader as YamlTranslationCatalogReader;
use App\Catalog\Infrastructure\Translation\TranslationCatalogWriter as YamlTranslationCatalogWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpKernel\KernelInterface;

final class ItemImportApplicationServiceTest extends TestCase
{
    private ItemImportPersistence&MockObject $persistence;
    private ItemImportItemRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->persistence = $this->createMock(ItemImportPersistence::class);
        $this->repository = $this->createMock(ItemImportItemRepository::class);
    }

    public function testDryRunComputesStatsWithoutPersistingOrWritingCatalog(): void
    {
        $root = $this->createTempDir();
        file_put_contents($root.'/minerva_61_alpha.json', '[{"id":61,"name_en":"Plan A"}]');
        file_put_contents($root.'/legendary_mods_1_broken.json', '{invalid}');

        $this->persistence->expects(self::never())->method('persist');
        $this->persistence->expects(self::never())->method('flush');
        $this->repository->expects(self::never())->method('findOneByTypeAndSourceId');
        $this->repository->expects(self::never())->method('findBooksByExternalRef');
        $this->repository->expects(self::never())->method('deleteAllBookLists');

        $projectDir = $this->createTempDir();
        $service = $this->createService($this->createTranslationReader($projectDir), $this->createTranslationWriter($projectDir));
        $result = $service->import($root, true, 100, true);
        $stats = $result->getStats();

        self::assertSame(2, $stats['files']);
        self::assertSame(1, $stats['rows']);
        self::assertSame(1, $stats['created']);
        self::assertSame(0, $stats['updated']);
        self::assertSame(1, $stats['errors']);
        self::assertSame(1, $stats['translations_en']);
        self::assertSame(1, $stats['translations_de']);
        self::assertCount(1, $result->getWarnings());
    }

    public function testWriteModePersistsFlushesAndWritesCatalogs(): void
    {
        $root = $this->createTempDir();
        file_put_contents($root.'/legendary_mods_1_alpha.json', '[{"id":10,"name_en":"Mod A","form_id":"0052E485","wiki_url":"https://example.test/wiki/mod-a"}]');

        $this->repository
            ->expects(self::once())
            ->method('findOneByTypeAndSourceId')
            ->willReturn(null);
        $this->repository
            ->expects(self::never())
            ->method('findBooksByExternalRef')
            ->willReturn([]);
        $this->repository
            ->expects(self::once())
            ->method('deleteAllBookLists')
            ->willReturn(0);

        $persistedItem = null;
        $this->persistence->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (ItemEntity $item) use (&$persistedItem): void {
                $persistedItem = $item;
            });
        $this->persistence->expects(self::once())->method('flush');
        $projectDir = $this->createTempDir();
        $service = $this->createService($this->createTranslationReader($projectDir), $this->createTranslationWriter($projectDir));
        $result = $service->import($root, false, 100, true);

        self::assertFalse($result->hasErrors());
        self::assertSame(1, $result->getStats()['created']);
        self::assertInstanceOf(ItemEntity::class, $persistedItem);
        self::assertCount(1, $persistedItem->getExternalSources());
        $externalSource = $persistedItem->getExternalSources()->first();
        self::assertNotFalse($externalSource);
        self::assertSame('nukaknights', $externalSource->getProvider());
        self::assertSame('0052E485', $externalSource->getExternalRef());
        self::assertSame('https://example.test/wiki/mod-a', $externalSource->getExternalUrl());
        self::assertFileExists($projectDir.'/translations/items.en.yaml');
        self::assertFileExists($projectDir.'/translations/items.de.yaml');
    }

    public function testDuplicateSourceIdInSameFileIsKeptWithWarning(): void
    {
        $root = $this->createTempDir();
        file_put_contents($root.'/minerva_84_alpha.json', '[{"id":931,"name_en":"Plan A"},{"id":931,"name_en":"Plan A Duplicate"}]');

        $this->repository
            ->expects(self::once())
            ->method('findOneByTypeAndSourceId')
            ->willReturn(null);
        $this->repository
            ->expects(self::never())
            ->method('findBooksByExternalRef')
            ->willReturn([]);
        $this->repository->expects(self::never())->method('deleteAllBookLists');

        $this->persistence->expects(self::exactly(2))->method('persist');
        $this->persistence->expects(self::once())->method('flush');

        $projectDir = $this->createTempDir();
        $service = $this->createService($this->createTranslationReader($projectDir), $this->createTranslationWriter($projectDir));
        $result = $service->import($root, false, 100, false);
        $stats = $result->getStats();

        self::assertSame(2, $stats['rows']);
        self::assertSame(1, $stats['created']);
        self::assertSame(1, $stats['updated']);
        self::assertSame(0, $stats['skipped']);
        self::assertSame(1, $stats['warnings']);
        self::assertCount(1, $result->getWarnings());
        self::assertStringContainsString('Doublon detecte', $result->getWarnings()[0]);
    }

    public function testWriteModeMergesFandomAndFalloutWikiExternalSourcesOnSameFormId(): void
    {
        $root = $this->createTempDir();
        mkdir($root.'/data/sources/fandom/plan_recipes', 0o777, true);
        mkdir($root.'/data/sources/fallout_wiki/plan_recipes', 0o777, true);

        file_put_contents($root.'/data/sources/fandom/plan_recipes/recipes.json', (string) json_encode([
            'page' => 'Fallout_76_recipes',
            'url' => 'https://fallout.fandom.com/wiki/Fallout_76_recipes',
            'resources' => [
                [
                    'type' => 'recipe',
                    'slug' => 'recipe-delbert-company-tea',
                    'title' => "Recipe: Delbert's Company Tea",
                    'section' => 'Recipes',
                    'columns' => [
                        'form_id' => '003A2021',
                        'wiki_url' => 'https://fallout.fandom.com/wiki/Recipe:Delbert%27s_company_tea',
                    ],
                    'availability' => [
                        'vendors' => false,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($root.'/data/sources/fallout_wiki/plan_recipes/recipes.json', (string) json_encode([
            'page' => 'Fallout_76_Recipes',
            'url' => 'https://fallout.wiki/wiki/Fallout_76_Recipes',
            'resources' => [
                [
                    'type' => 'recipe',
                    'slug' => 'recipe-delbert-s-company-tea',
                    'name' => "Recipe: Delbert's Company Tea",
                    'section' => 'Recipes',
                    'columns' => [
                        'form_id' => '003A2021',
                        'wiki_url' => 'https://fallout.wiki/wiki/Recipe:Delbert%27s_Company_Tea',
                    ],
                    'availability' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->repository
            ->expects(self::once())
            ->method('findOneByTypeAndSourceId')
            ->willReturn(null);
        $this->repository
            ->expects(self::once())
            ->method('findBooksByExternalRef')
            ->willReturn([]);
        $this->repository->expects(self::never())->method('deleteAllBookLists');

        $persistedItems = [];
        $this->persistence->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (ItemEntity $item) use (&$persistedItems): void {
                $persistedItems[] = $item;
            });
        $this->persistence->expects(self::once())->method('flush');

        $projectDir = $this->createTempDir();
        $service = $this->createService($this->createTranslationReader($projectDir), $this->createTranslationWriter($projectDir));
        $result = $service->import($root, false, 100, false);

        self::assertFalse($result->hasErrors());
        self::assertSame(1, $result->getStats()['created']);
        self::assertSame(1, $result->getStats()['updated']);
        self::assertCount(2, $persistedItems);
        $item = $persistedItems[1];
        self::assertCount(2, $item->getExternalSources());
    }

    public function testWriteModeSkipsFalloutWikiRowsWithoutUsableFormId(): void
    {
        $root = $this->createTempDir();
        mkdir($root.'/data/sources/fallout_wiki/plan_recipes', 0o777, true);

        file_put_contents($root.'/data/sources/fallout_wiki/plan_recipes/plans_workshop.json', (string) json_encode([
            'page' => 'Fallout_76_Workshop_Plans',
            'url' => 'https://fallout.wiki/wiki/Fallout_76_Workshop_Plans',
            'resources' => [
                [
                    'type' => 'plan',
                    'slug' => 'plan-valid-workbench',
                    'name' => 'Plan: Valid Workbench',
                    'section' => 'Workshop',
                    'columns' => [
                        'form_id' => '005D0095',
                        'wiki_url' => 'https://fallout.wiki/wiki/Plan:Valid_Workbench',
                    ],
                    'availability' => [],
                ],
                [
                    'type' => 'plan',
                    'slug' => 'plan-missing-form-id',
                    'name' => 'Plan: Missing Form ID',
                    'section' => 'Workshop',
                    'columns' => [
                        'wiki_url' => 'https://fallout.wiki/wiki/Plan:Missing_Form_ID',
                    ],
                    'availability' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->repository
            ->expects(self::once())
            ->method('findOneByTypeAndSourceId')
            ->willReturn(null);
        $this->repository
            ->expects(self::once())
            ->method('findBooksByExternalRef')
            ->willReturn([]);
        $this->repository->expects(self::never())->method('deleteAllBookLists');

        $this->persistence->expects(self::once())->method('persist');
        $this->persistence->expects(self::once())->method('flush');

        $projectDir = $this->createTempDir();
        $service = $this->createService($this->createTranslationReader($projectDir), $this->createTranslationWriter($projectDir));
        $result = $service->import($root, false, 100, false);
        $stats = $result->getStats();

        self::assertFalse($result->hasErrors());
        self::assertSame(2, $stats['rows']);
        self::assertSame(1, $stats['created']);
        self::assertSame(1, $stats['skipped']);
        self::assertSame(0, $stats['errors']);
        self::assertSame(1, $stats['warnings']);
        self::assertCount(1, $result->getWarnings());
        self::assertStringContainsString('sans form_id exploitable', $result->getWarnings()[0]);
    }

    public function testWriteModeSkipsDuplicateWikiProviderRowsForSameFormId(): void
    {
        $root = $this->createTempDir();
        mkdir($root.'/data/sources/fallout_wiki/plan_recipes', 0o777, true);

        file_put_contents($root.'/data/sources/fallout_wiki/plan_recipes/plans_weapon_mods.json', (string) json_encode([
            'page' => 'Fallout_76_Weapon_Mod_Plans',
            'url' => 'https://fallout.wiki/wiki/Fallout_76_Weapon_Mod_Plans',
            'resources' => [
                [
                    'type' => 'plan',
                    'slug' => 'plan-bladed-commie-whacker',
                    'name' => 'Plan: Bladed Commie Whacker',
                    'section' => 'Melee',
                    'columns' => [
                        'form_id' => '002B42A4',
                        'wiki_url' => 'https://fallout.wiki/wiki/Plan%3A_Bladed_Commie_Whacker',
                    ],
                    'availability' => [],
                ],
                [
                    'type' => 'plan',
                    'slug' => 'plan-garden-trowel-knife',
                    'name' => 'Plan: Garden Trowel Knife',
                    'section' => 'Melee',
                    'columns' => [
                        'form_id' => '002B42A4',
                        'wiki_url' => 'https://fallout.wiki/wiki/Plan%3A_Garden_Trowel_Knife',
                    ],
                    'availability' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->repository
            ->expects(self::once())
            ->method('findOneByTypeAndSourceId')
            ->willReturn(null);
        $this->repository
            ->expects(self::once())
            ->method('findBooksByExternalRef')
            ->willReturn([]);
        $this->repository->expects(self::never())->method('deleteAllBookLists');

        $persistedItems = [];
        $this->persistence->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (ItemEntity $item) use (&$persistedItems): void {
                $persistedItems[] = $item;
            });
        $this->persistence->expects(self::once())->method('flush');

        $projectDir = $this->createTempDir();
        $service = $this->createService($this->createTranslationReader($projectDir), $this->createTranslationWriter($projectDir));
        $result = $service->import($root, false, 100, false);
        $stats = $result->getStats();

        self::assertFalse($result->hasErrors());
        self::assertSame(2, $stats['rows']);
        self::assertSame(1, $stats['created']);
        self::assertSame(1, $stats['skipped']);
        self::assertSame(1, $stats['warnings']);
        self::assertCount(1, $persistedItems);
        self::assertSame('item.book.2835108.name', $persistedItems[0]->getNameKey());
        self::assertStringContainsString('Doublon form_id detecte', $result->getWarnings()[0]);
    }

    public function testWriteModeReconcilesBookDuplicatesBySharedFormId(): void
    {
        $root = $this->createTempDir();
        file_put_contents($root.'/minerva_77_alpha.json', '[{"id":889,"type":"BOOK","form_id":"00589621","name_en":"Plan: Cattle prod","vendor_samuel":1,"price":250,"price_minerva":188,"wiki_url":"https://example.test/wiki/cattle-prod"}]');

        $keeper = new ItemEntity()
            ->setType(\App\Catalog\Domain\Item\ItemTypeEnum::BOOK)
            ->setSourceId(5805601)
            ->setNameKey('item.book.5805601.name');
        $this->setEntityId($keeper, 101);
        $keeper->upsertExternalSource('fandom', '00589621', 'https://example.test/fandom/cattle-prod', [
            'name' => 'Plan: Cattle prod',
        ]);

        $duplicate = new ItemEntity()
            ->setType(\App\Catalog\Domain\Item\ItemTypeEnum::BOOK)
            ->setSourceId(889)
            ->setNameKey('item.book.889.name')
            ->setVendorSamuel(true)
            ->setPrice(250)
            ->setPriceMinerva(188);
        $this->setEntityId($duplicate, 202);
        $duplicate->addBookList(1, false);
        $duplicate->upsertExternalSource('nukaknights', '00589621', 'https://example.test/wiki/cattle-prod', [
            'name_en' => 'Plan: Cattle prod',
        ]);

        $this->repository
            ->expects(self::once())
            ->method('findOneByTypeAndSourceId')
            ->willReturn($duplicate);
        $this->repository
            ->expects(self::once())
            ->method('findBooksByExternalRef')
            ->with('00589621')
            ->willReturn([$duplicate, $keeper]);
        $this->repository->expects(self::never())->method('deleteAllBookLists');

        $this->persistence->expects(self::once())
            ->method('mergeBookDuplicate')
            ->with($duplicate, $keeper);
        $persistedItem = null;
        $this->persistence->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (ItemEntity $item) use (&$persistedItem): void {
                $persistedItem = $item;
            });
        $this->persistence->expects(self::once())->method('flush');

        $projectDir = $this->createTempDir();
        $translationWriter = $this->createTranslationWriter($projectDir);
        $translationReader = $this->createTranslationReader($projectDir);
        $translationWriter->upsert('en', 'items', [
            'item.book.889.name' => 'Plan: Cattle prod',
        ]);
        $translationWriter->upsert('de', 'items', [
            'item.book.889.name' => 'Bauplan: Viehtreiber',
        ]);
        $service = $this->createService($translationReader, $translationWriter);
        $result = $service->import($root, false, 100, false);

        self::assertFalse($result->hasErrors());
        self::assertSame(0, $result->getStats()['created']);
        self::assertSame(1, $result->getStats()['updated']);
        self::assertSame($keeper, $persistedItem);
        self::assertSame('item.book.5805601.name', $keeper->getNameKey());
        self::assertTrue($keeper->isVendorSamuel());
        self::assertSame([1, 17], array_map(
            static fn ($bookList): int => $bookList->getListNumber(),
            $keeper->getBookLists()->toArray(),
        ));
        self::assertCount(2, $keeper->getExternalSources());
        self::assertSame('Plan: Cattle prod', $translationReader->load('en', 'items')['item.book.5805601.name'] ?? null);
        self::assertSame('Bauplan: Viehtreiber', $translationReader->load('de', 'items')['item.book.5805601.name'] ?? null);
        self::assertArrayNotHasKey('item.book.889.name', $translationReader->load('en', 'items'));
        self::assertArrayNotHasKey('item.book.889.name', $translationReader->load('de', 'items'));
    }

    private function createService(TranslationCatalogReader $translationCatalogReader, TranslationCatalogWriter $translationCatalogWriter): ItemImportApplicationService
    {
        $normalizer = new ItemImportValueNormalizer();

        return new ItemImportApplicationService(
            $this->persistence,
            $this->repository,
            $translationCatalogReader,
            $translationCatalogWriter,
            new ItemImportFileContextResolver(),
            new FilesystemItemImportSourceReader(),
            new ItemImportItemHydrator(
                $normalizer,
                new ItemImportExternalUrlResolver($normalizer),
                new ItemImportExternalMetadataEnricher(),
            ),
            new ItemImportTranslationCatalogBuilder($normalizer),
            new ItemImportContextApplier(),
        );
    }

    private function createTranslationWriter(string $projectDir): TranslationCatalogWriter
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        return new YamlTranslationCatalogWriter($kernel);
    }

    private function createTranslationReader(string $projectDir): TranslationCatalogReader
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        return new YamlTranslationCatalogReader($kernel);
    }

    private function createTempDir(): string
    {
        $path = sys_get_temp_dir().'/item-import-service-'.bin2hex(random_bytes(8));
        mkdir($path, 0o777, true);

        return $path;
    }

    private function setEntityId(ItemEntity $item, int $id): void
    {
        $reflection = new ReflectionProperty($item, 'id');
        $reflection->setValue($item, $id);
    }
}
