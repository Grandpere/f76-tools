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

use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Repository\ItemEntityRepository;
use App\Service\PlayerItemKnowledgeManager;

final class PlayerKnowledgeApplicationService
    implements OwnedPlayerResolverInterface
{
    public function __construct(
        private readonly PlayerItemKnowledgeManager $knowledgeManager,
        private readonly ItemEntityRepository $itemRepository,
    ) {
    }

    public function resolveOwnedPlayer(UserEntity $user, string $playerPublicId): ?PlayerEntity
    {
        return $this->knowledgeManager->resolveOwnedPlayer($playerPublicId, $user);
    }

    public function resolveItemByPublicId(string $itemPublicId): ?ItemEntity
    {
        return $this->itemRepository->findOneByPublicId($itemPublicId);
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
