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

use App\Catalog\Domain\Item\ItemTypeEnum;

final class ItemImportFileContextResolver
{
    /**
     * @return array{type: ItemTypeEnum, rank: int|null, listNumber: int|null, isSpecialList: bool}|null
     */
    public function resolve(string $filePath): ?array
    {
        $filename = basename($filePath);

        if (1 === preg_match('/^legendary_mods_(\d+)_\w+\.json$/', $filename, $matches)) {
            return [
                'type' => ItemTypeEnum::MISC,
                'rank' => (int) $matches[1],
                'listNumber' => null,
                'isSpecialList' => false,
            ];
        }

        if (1 === preg_match('/^minerva_(\d+)_\w+\.json$/', $filename, $matches)) {
            $minervaNumber = (int) $matches[1];
            $listNumber = (($minervaNumber - 61) % 4) + 1;
            $isSpecialList = 4 === $listNumber;

            return [
                'type' => ItemTypeEnum::BOOK,
                'rank' => null,
                'listNumber' => $listNumber,
                'isSpecialList' => $isSpecialList,
            ];
        }

        return null;
    }
}
