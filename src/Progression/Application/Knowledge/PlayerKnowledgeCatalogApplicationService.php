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
use App\Progression\Domain\Entity\PlayerEntity;

final class PlayerKnowledgeCatalogApplicationService
{
    public function __construct(
        private readonly ItemKnowledgeCatalogReadRepository $itemRepository,
        private readonly PlayerKnowledgeCatalogReadRepository $knowledgeRepository,
    ) {
    }

    /**
     * @return list<array{item: \App\Catalog\Domain\Entity\ItemEntity, learned: bool}>
     */
    public function listForPlayer(PlayerEntity $player, ?ItemTypeEnum $type = null): array
    {
        $items = $this->itemRepository->findAllOrdered($type);
        $learnedMap = array_fill_keys($this->knowledgeRepository->findLearnedItemIdsByPlayer($player), true);

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'item' => $item,
                'learned' => isset($learnedMap[$item->getId() ?? 0]),
            ];
        }

        return $rows;
    }
}
