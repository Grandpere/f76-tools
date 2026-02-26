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

use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;

interface PlayerKnowledgeTransferRepositoryInterface
{
    /**
     * @return list<ItemEntity>
     */
    public function findLearnedItemsByPlayer(PlayerEntity $player): array;

    /**
     * @return list<int>
     */
    public function findLearnedItemIdsByPlayer(PlayerEntity $player): array;

    public function countLearnedByPlayer(PlayerEntity $player): int;

    /**
     * @param list<int> $itemIds
     */
    public function deleteByPlayerAndItemIds(PlayerEntity $player, array $itemIds): int;
}
