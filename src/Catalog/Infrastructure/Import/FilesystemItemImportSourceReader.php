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

namespace App\Catalog\Infrastructure\Import;

use App\Catalog\Application\Import\ItemImportSourceReaderInterface;
use JsonException;
use Symfony\Component\Finder\Finder;

final class FilesystemItemImportSourceReader implements ItemImportSourceReaderInterface
{
    /**
     * @return list<string>
     */
    public function findImportFiles(string $rootPath): array
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($rootPath)
            ->name('*.json')
            ->notName('manifest.json');

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath() ?: $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<mixed>|null
     */
    public function readRows(string $path): ?array
    {
        try {
            $json = file_get_contents($path);
            if (false === $json) {
                return null;
            }

            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }
}
