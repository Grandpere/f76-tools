<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Import;

use App\Catalog\Application\Import\ItemImportJsonFileReader;
use PHPUnit\Framework\TestCase;

final class ItemImportJsonFileReaderTest extends TestCase
{
    public function testFindImportFilesReturnsSortedJsonWithoutManifest(): void
    {
        $root = $this->createTempDir();
        file_put_contents($root.'/legendary_mods_1_alpha.json', '[]');
        file_put_contents($root.'/minerva_61_beta.json', '[]');
        file_put_contents($root.'/manifest.json', '{}');

        $reader = new ItemImportJsonFileReader();
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

        $reader = new ItemImportJsonFileReader();
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

        $reader = new ItemImportJsonFileReader();

        self::assertNull($reader->readRows($path));
    }

    public function testReadRowsReturnsNullForNonArrayJson(): void
    {
        $root = $this->createTempDir();
        $path = $root.'/items.json';
        file_put_contents($path, '"value"');

        $reader = new ItemImportJsonFileReader();

        self::assertNull($reader->readRows($path));
    }

    private function createTempDir(): string
    {
        $path = sys_get_temp_dir().'/item-import-reader-'.bin2hex(random_bytes(8));
        mkdir($path, 0777, true);

        return $path;
    }
}
