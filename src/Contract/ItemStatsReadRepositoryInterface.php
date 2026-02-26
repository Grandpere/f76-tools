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

namespace App\Contract;

use App\Domain\Item\ItemTypeEnum;

interface ItemStatsReadRepositoryInterface
{
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
}
