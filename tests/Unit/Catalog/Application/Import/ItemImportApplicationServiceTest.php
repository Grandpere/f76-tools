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
use App\Catalog\Application\Import\ItemImportFileContextResolver;
use App\Catalog\Application\Import\ItemImportItemHydrator;
use App\Catalog\Application\Import\ItemImportItemRepositoryInterface;
use App\Catalog\Application\Import\ItemImportPersistenceInterface;
use App\Catalog\Application\Import\ItemImportTranslationCatalogBuilder;
use App\Catalog\Application\Import\ItemImportValueNormalizer;
use App\Catalog\Application\Translation\TranslationCatalogWriter;
use App\Catalog\Infrastructure\Import\FilesystemItemImportSourceReader;
use App\Catalog\Infrastructure\Translation\TranslationCatalogWriter as YamlTranslationCatalogWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class ItemImportApplicationServiceTest extends TestCase
{
    private ItemImportPersistenceInterface&MockObject $persistence;
    private ItemImportItemRepositoryInterface&MockObject $repository;

    protected function setUp(): void
    {
        $this->persistence = $this->createMock(ItemImportPersistenceInterface::class);
        $this->repository = $this->createMock(ItemImportItemRepositoryInterface::class);
    }

    public function testDryRunComputesStatsWithoutPersistingOrWritingCatalog(): void
    {
        $root = $this->createTempDir();
        file_put_contents($root.'/minerva_61_alpha.json', '[{"id":61,"name_en":"Plan A"}]');
        file_put_contents($root.'/legendary_mods_1_broken.json', '{invalid}');

        $this->persistence->expects(self::never())->method('persist');
        $this->persistence->expects(self::never())->method('flush');
        $this->repository->expects(self::never())->method('findOneByTypeAndSourceId');

        $service = $this->createService($this->createTranslationWriter($this->createTempDir()));
        $result = $service->import($root, true, 100);
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
        file_put_contents($root.'/legendary_mods_1_alpha.json', '[{"id":10,"name_en":"Mod A"}]');

        $this->repository
            ->expects(self::once())
            ->method('findOneByTypeAndSourceId')
            ->willReturn(null);

        $this->persistence->expects(self::once())->method('persist');
        $this->persistence->expects(self::once())->method('flush');
        $projectDir = $this->createTempDir();
        $service = $this->createService($this->createTranslationWriter($projectDir));
        $result = $service->import($root, false, 100);

        self::assertFalse($result->hasErrors());
        self::assertSame(1, $result->getStats()['created']);
        self::assertFileExists($projectDir.'/translations/items.en.yaml');
        self::assertFileExists($projectDir.'/translations/items.de.yaml');
    }

    private function createService(TranslationCatalogWriter $translationCatalogWriter): ItemImportApplicationService
    {
        $normalizer = new ItemImportValueNormalizer();

        return new ItemImportApplicationService(
            $this->persistence,
            $this->repository,
            $translationCatalogWriter,
            new ItemImportFileContextResolver(),
            new FilesystemItemImportSourceReader(),
            new ItemImportItemHydrator($normalizer),
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

    private function createTempDir(): string
    {
        $path = sys_get_temp_dir().'/item-import-service-'.bin2hex(random_bytes(8));
        mkdir($path, 0o777, true);

        return $path;
    }
}
