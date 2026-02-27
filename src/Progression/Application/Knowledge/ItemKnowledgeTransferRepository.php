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

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;

interface ItemKnowledgeTransferRepository
{
    /**
     * @param list<int> $sourceIds
     *
     * @return list<ItemEntity>
     */
    public function findByTypeAndSourceIds(ItemTypeEnum $type, array $sourceIds): array;

    /**
     * @param list<int> $ids
     *
     * @return list<ItemEntity>
     */
    public function findByIds(array $ids): array;
}
