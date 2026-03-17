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

namespace App\Tests\Unit\Catalog\Infrastructure\Import;

use App\Catalog\Infrastructure\Import\FilesystemItemImportSourceReader;
use PHPUnit\Framework\TestCase;

final class FilesystemItemImportSourceReaderTest extends TestCase
{
    public function testFindImportFilesReturnsSortedJsonWithoutManifest(): void
    {
        $root = $this->createTempDir();
        file_put_contents($root.'/legendary_mods_1_alpha.json', '[]');
        file_put_contents($root.'/minerva_61_beta.json', '[]');
        file_put_contents($root.'/manifest.json', '{}');
        file_put_contents($root.'/index.json', '{}');

        $reader = new FilesystemItemImportSourceReader();
        $files = $reader->findImportFiles($root);

        self::assertCount(2, $files);
        self::assertStringEndsWith('legendary_mods_1_alpha.json', $files[0]);
        self::assertStringEndsWith('minerva_61_beta.json', $files[1]);
    }

    public function testReadRowsReturnsArrayForValidJsonList(): void
    {
        $root = $this->createTempDir();
        $path = $root.'/items.json';
        file_put_contents($path, '[{"id":1}]');

        $reader = new FilesystemItemImportSourceReader();
        $rows = $reader->readRows($path);

        self::assertIsArray($rows);
        self::assertIsArray($rows[0]);
        self::assertArrayHasKey('id', $rows[0]);
        self::assertSame(1, $rows[0]['id']);
    }

    public function testReadRowsReturnsNullForInvalidJson(): void
    {
        $root = $this->createTempDir();
        $path = $root.'/items.json';
        file_put_contents($path, '{invalid}');

        $reader = new FilesystemItemImportSourceReader();

        self::assertNull($reader->readRows($path));
    }

    public function testReadRowsReturnsNullForNonArrayJson(): void
    {
        $root = $this->createTempDir();
        $path = $root.'/items.json';
        file_put_contents($path, '"value"');

        $reader = new FilesystemItemImportSourceReader();

        self::assertNull($reader->readRows($path));
    }

    public function testReadRowsReturnsNormalizedResourcesForObjectPayload(): void
    {
        $root = $this->createTempDir();
        $path = $root.'/recipes.json';
        file_put_contents($path, (string) json_encode([
            'generated_at' => '2026-03-17T10:00:00+00:00',
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
                    'availability' => [
                        'vendors' => true,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $reader = new FilesystemItemImportSourceReader();
        $rows = $reader->readRows($path);

        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertIsArray($rows[0]);
        self::assertSame(3809313, $rows[0]['id'] ?? null);
        self::assertSame("Recipe: Delbert's Company Tea", $rows[0]['name_en'] ?? null);
        self::assertSame('Recipes', $rows[0]['source_section'] ?? null);
        self::assertTrue((bool) ($rows[0]['vendors'] ?? false));
    }

    private function createTempDir(): string
    {
        $path = sys_get_temp_dir().'/item-import-reader-'.bin2hex(random_bytes(8));
        mkdir($path, 0o777, true);

        return $path;
    }
}
