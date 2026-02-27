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

final class PlayerKnowledgeWriteApplicationService
{
    public function __construct(
        private readonly PlayerItemKnowledgeManager $knowledgeManager,
    ) {
    }

    public function markLearned(PlayerEntity $player, ItemEntity $item): void
    {
        $this->knowledgeManager->setLearned($player, $item);
    }

    public function unmarkLearned(PlayerEntity $player, ItemEntity $item): void
    {
        $this->knowledgeManager->unsetLearned($player, $item);
    }
}
