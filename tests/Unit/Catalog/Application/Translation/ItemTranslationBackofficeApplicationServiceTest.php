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

namespace App\Tests\Unit\Catalog\Application\Translation;

use App\Catalog\Application\Translation\ItemTranslationBackofficeApplicationService;
use App\Catalog\Application\Translation\TranslationCatalogReader;
use App\Catalog\Application\Translation\TranslationCatalogWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ItemTranslationBackofficeApplicationServiceTest extends TestCase
{
    public function testSaveTargetEntriesNormalizesAndPersistsValues(): void
    {
        /** @var TranslationCatalogReader&MockObject $reader */
        $reader = $this->createMock(TranslationCatalogReader::class);
        /** @var TranslationCatalogWriter&MockObject $writer */
        $writer = $this->createMock(TranslationCatalogWriter::class);
        $service = new ItemTranslationBackofficeApplicationService($reader, $writer);

        $writer
            ->expects(self::once())
            ->method('upsert')
            ->with('fr', 'items', [
                'item.misc.10.name' => 'Nom FR',
                'item.book.250.name' => 'Plan FR',
            ]);

        $count = $service->saveTargetEntries('fr', [
            'item.misc.10.name' => ' Nom FR ',
            'item.book.250.name' => 'Plan FR',
            'item.misc.10.desc' => '',
            '' => 'ignored',
        ]);

        self::assertSame(2, $count);
    }

    public function testBuildRowsFiltersByQueryAndSection(): void
    {
        /** @var TranslationCatalogReader&MockObject $reader */
        $reader = $this->createMock(TranslationCatalogReader::class);
        /** @var TranslationCatalogWriter&MockObject $writer */
        $writer = $this->createMock(TranslationCatalogWriter::class);
        $service = new ItemTranslationBackofficeApplicationService($reader, $writer);

        $reader
            ->method('load')
            ->willReturnMap([
                ['en', 'items', [
                    'item.misc.10.name' => 'Legendary Mod',
                    'item.book.250.name' => 'Minerva Plan',
                    'random.key' => 'Ignored',
                ]],
                ['de', 'items', [
                    'item.misc.10.name' => 'Legendarer Mod',
                    'item.book.250.name' => 'Minerva Plan DE',
                ]],
                ['fr', 'items', [
                    'item.book.250.name' => 'Plan FR',
                ]],
            ]);

        $rows = $service->buildRows('fr', 'plan fr');

        self::assertCount(1, $rows);
        self::assertSame('item.book.250.name', $rows[0]['key']);
        self::assertSame('book', $rows[0]['section']);
        self::assertSame('Plan FR', $rows[0]['target']);
    }
}
