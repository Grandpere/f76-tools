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
use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Application\Player\PlayerByOwnerFinder;
use App\Progression\Domain\Entity\PlayerEntity;
use App\Progression\Domain\Entity\PlayerItemKnowledgeEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class PlayerItemKnowledgeManager
{
    public function __construct(
        private readonly PlayerByOwnerFinder $playerFinder,
        private readonly PlayerItemKnowledgeFinder $knowledgeFinder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolveOwnedPlayer(string $playerId, UserEntity $user): ?PlayerEntity
    {
        return $this->playerFinder->findOneByPublicIdAndUser($playerId, $user);
    }

    public function setLearned(PlayerEntity $player, ItemEntity $item): void
    {
        $knowledge = $this->knowledgeFinder->findOneByPlayerAndItem($player, $item);
        if ($knowledge instanceof PlayerItemKnowledgeEntity) {
            return;
        }

        $knowledge = new PlayerItemKnowledgeEntity()
            ->setPlayer($player)
            ->setItem($item)
            ->setLearnedAt(new DateTimeImmutable());

        $this->entityManager->persist($knowledge);
        $this->entityManager->flush();
    }

    public function unsetLearned(PlayerEntity $player, ItemEntity $item): void
    {
        $knowledge = $this->knowledgeFinder->findOneByPlayerAndItem($player, $item);
        if (!$knowledge instanceof PlayerItemKnowledgeEntity) {
            return;
        }

        $this->entityManager->remove($knowledge);
        $this->entityManager->flush();
    }
}
