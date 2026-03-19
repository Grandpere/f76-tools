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

namespace App\Progression\Application\Knowledge;

use App\Catalog\Domain\Item\ItemTypeEnum;

interface ItemStatsReadRepository
{
    /**
     * @return array{all: int, misc: int, book: int}
     */
    public function countAllByType(): array;

    public function countAll(): int;

    public function countByType(ItemTypeEnum $type): int;

    /**
     * @return array<int, int>
     */
    public function findMiscTotalsByRank(): array;

    /**
     * @return array<int, int>
     */
    public function findBookTotalsByListNumber(): array;

    public function countBooksWithListNumber(): int;

    /**
     * @return array{plan: int, recipe: int}
     */
    public function findBookTotalsByKind(): array;

    /**
     * @return array<string, int>
     */
    public function findBookTotalsByCategory(): array;
}
