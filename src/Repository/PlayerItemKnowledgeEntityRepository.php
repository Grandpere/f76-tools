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

namespace App\Repository;

use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Entity\PlayerItemKnowledgeEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlayerItemKnowledgeEntity>
 */
final class PlayerItemKnowledgeEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayerItemKnowledgeEntity::class);
    }

    public function findOneByPlayerAndItem(PlayerEntity $player, ItemEntity $item): ?PlayerItemKnowledgeEntity
    {
        $knowledge = $this->findOneBy([
            'player' => $player,
            'item' => $item,
        ]);

        return $knowledge instanceof PlayerItemKnowledgeEntity ? $knowledge : null;
    }

    /**
     * @return list<int>
     */
    public function findLearnedItemIdsByPlayer(PlayerEntity $player): array
    {
        $rows = $this->createQueryBuilder('k')
            ->select('IDENTITY(k.item) AS itemId')
            ->andWhere('k.player = :player')
            ->setParameter('player', $player)
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $itemId = $row['itemId'] ?? null;
            if (is_int($itemId) || is_numeric($itemId)) {
                $ids[] = (int) $itemId;
            }
        }

        return $ids;
    }
}

