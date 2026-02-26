<?php

declare(strict_types=1);

namespace App\Catalog\Application\Import;

interface ItemImportSourceReaderInterface
{
    /**
     * @return list<string>
     */
    public function findImportFiles(string $rootPath): array;

    /**
     * @return array<mixed>|null
     */
    public function readRows(string $path): ?array;
}
