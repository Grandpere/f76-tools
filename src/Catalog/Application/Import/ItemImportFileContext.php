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

final readonly class ItemImportFileContext
{
    public function __construct(
        public ItemTypeEnum $type,
        public ?int $rank,
        public ?int $listNumber,
        public bool $isSpecialList,
        public string $sourceProvider,
    ) {
    }

    public static function misc(int $rank, string $sourceProvider): self
    {
        return new self(
            ItemTypeEnum::MISC,
            $rank,
            null,
            false,
            $sourceProvider,
        );
    }

    public static function book(int $listNumber, bool $isSpecialList, string $sourceProvider): self
    {
        return new self(
            ItemTypeEnum::BOOK,
            null,
            $listNumber,
            $isSpecialList,
            $sourceProvider,
        );
    }
}
