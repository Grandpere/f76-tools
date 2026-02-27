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

use App\Catalog\Domain\Entity\ItemEntity;
use App\Progression\Domain\Entity\PlayerEntity;

interface PlayerKnowledgeTransferRepository
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
