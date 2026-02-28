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

namespace App\Catalog\Application\Import;

interface ItemImportSourceReader
{
    /**
     * @return list<string>
     */
    public function findImportFiles(string $rootPath): array;

    /**
     * @return list<mixed>|null
     */
    public function readRows(string $path): ?array;
}
