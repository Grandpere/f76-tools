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

interface PlayerKnowledgeCatalogReadRepository
{
    /**
     * @return list<int>
     */
    public function findLearnedItemIdsByPlayer(PlayerEntity $player, ?ItemTypeEnum $type = null): array;
}
