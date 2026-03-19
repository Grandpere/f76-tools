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

interface PlayerKnowledgeStatsReadRepository
{
    /**
     * @return array{all: int, misc: int, book: int}
     */
    public function countLearnedByPlayerByType(PlayerEntity $player): array;

    public function countLearnedByPlayer(PlayerEntity $player): int;

    public function countLearnedByPlayerAndType(PlayerEntity $player, ItemTypeEnum $type): int;

    /**
     * @return array<int, int>
     */
    public function findLearnedMiscCountsByRank(PlayerEntity $player): array;

    /**
     * @return array<int, int>
     */
    public function findLearnedBookCountsByListNumber(PlayerEntity $player): array;

    public function countLearnedBooksWithListNumber(PlayerEntity $player): int;

    /**
     * @return array{plan: int, recipe: int}
     */
    public function findLearnedBookCountsByKind(PlayerEntity $player): array;

    /**
     * @return array<string, int>
     */
    public function findLearnedBookCountsByCategory(PlayerEntity $player): array;
}
