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

final class ItemImportFileContextResolver
{
    public function resolve(string $filePath): ?ItemImportFileContext
    {
        $filename = basename($filePath);
        $normalizedPath = str_replace('\\', '/', $filePath);

        if (1 === preg_match('/^legendary_mods_(\d+)_\w+\.json$/', $filename, $matches)) {
            return ItemImportFileContext::misc((int) $matches[1], 'nukaknights');
        }

        if (1 === preg_match('/^minerva_(\d+)_\w+\.json$/', $filename, $matches)) {
            $minervaNumber = (int) $matches[1];
            $listNumber = $minervaNumber - 60;
            if ($listNumber < 1) {
                return null;
            }
            $isSpecialList = 0 === $listNumber % 4;

            return ItemImportFileContext::book($listNumber, $isSpecialList, 'nukaknights');
        }

        if (str_contains($normalizedPath, '/data/sources/fandom/plan_recipes/')
            && (1 === preg_match('/^recipes\.json$/', $filename) || 1 === preg_match('/^plans_.+\.json$/', $filename))) {
            return ItemImportFileContext::bookCatalog('fandom');
        }

        if (str_contains($normalizedPath, '/data/sources/fallout_wiki/plan_recipes/')
            && (1 === preg_match('/^recipes\.json$/', $filename) || 1 === preg_match('/^plans_.+\.json$/', $filename))) {
            return ItemImportFileContext::bookCatalog('fallout_wiki');
        }

        return null;
    }
}
