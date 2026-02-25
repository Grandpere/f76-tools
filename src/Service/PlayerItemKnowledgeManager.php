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

namespace App\Service;

use App\Contract\PlayerByOwnerFinderInterface;
use App\Contract\PlayerItemKnowledgeFinderInterface;
use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Entity\PlayerItemKnowledgeEntity;
use App\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class PlayerItemKnowledgeManager
{
    public function __construct(
        private readonly PlayerByOwnerFinderInterface $playerFinder,
        private readonly PlayerItemKnowledgeFinderInterface $knowledgeFinder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolveOwnedPlayer(int $playerId, UserEntity $user): ?PlayerEntity
    {
        return $this->playerFinder->findOneByIdAndUser($playerId, $user);
    }

    public function setLearned(PlayerEntity $player, ItemEntity $item): void
    {
        $knowledge = $this->knowledgeFinder->findOneByPlayerAndItem($player, $item);
        if ($knowledge instanceof PlayerItemKnowledgeEntity) {
            return;
        }

        $knowledge = (new PlayerItemKnowledgeEntity())
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
