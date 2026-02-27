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

namespace App\Progression\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Progression\Application\Knowledge\PlayerItemKnowledgeFinder;
use App\Progression\Application\Knowledge\PlayerKnowledgeCatalogReadRepository;
use App\Progression\Application\Knowledge\PlayerKnowledgeStatsReadRepository;
use App\Progression\Application\Knowledge\PlayerKnowledgeTransferRepository;
use App\Progression\Domain\Entity\PlayerEntity;
use App\Progression\Domain\Entity\PlayerItemKnowledgeEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlayerItemKnowledgeEntity>
 */
final class PlayerItemKnowledgeEntityRepository extends ServiceEntityRepository implements PlayerItemKnowledgeFinder, PlayerKnowledgeTransferRepository, PlayerKnowledgeStatsReadRepository, PlayerKnowledgeCatalogReadRepository
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

    public function countLearnedByPlayer(PlayerEntity $player): int
    {
        $count = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.player = :player')
            ->setParameter('player', $player)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @param list<int> $itemIds
     */
    public function deleteByPlayerAndItemIds(PlayerEntity $player, array $itemIds): int
    {
        if ([] === $itemIds) {
            return 0;
        }

        $deleted = $this->createQueryBuilder('k')
            ->delete(PlayerItemKnowledgeEntity::class, 'k')
            ->andWhere('k.player = :player')
            ->andWhere('IDENTITY(k.item) IN (:itemIds)')
            ->setParameter('player', $player)
            ->setParameter('itemIds', $itemIds)
            ->getQuery()
            ->execute();

        if (is_int($deleted) || is_numeric($deleted)) {
            return (int) $deleted;
        }

        return 0;
    }

    /**
     * @return list<ItemEntity>
     */
    public function findLearnedItemsByPlayer(PlayerEntity $player): array
    {
        $rows = $this->createQueryBuilder('k')
            ->addSelect('i')
            ->join('k.item', 'i')
            ->andWhere('k.player = :player')
            ->setParameter('player', $player)
            ->orderBy('i.type', 'ASC')
            ->addOrderBy('i.sourceId', 'ASC')
            ->getQuery()
            ->getResult();
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!$row instanceof PlayerItemKnowledgeEntity) {
                continue;
            }
            $items[] = $row->getItem();
        }

        return $items;
    }

    public function countLearnedByPlayerAndType(PlayerEntity $player, ItemTypeEnum $type): int
    {
        $count = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->join('k.item', 'i')
            ->andWhere('k.player = :player')
            ->andWhere('i.type = :type')
            ->setParameter('player', $player)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return array<int, int>
     */
    public function findLearnedMiscCountsByRank(PlayerEntity $player): array
    {
        $rows = $this->createQueryBuilder('k')
            ->select('i.rank AS rank')
            ->addSelect('COUNT(k.id) AS learnedCount')
            ->join('k.item', 'i')
            ->andWhere('k.player = :player')
            ->andWhere('i.type = :type')
            ->setParameter('player', $player)
            ->setParameter('type', ItemTypeEnum::MISC)
            ->groupBy('i.rank')
            ->orderBy('i.rank', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rankRaw = $row['rank'] ?? null;
            $countRaw = $row['learnedCount'] ?? null;
            if ((!is_int($rankRaw) && !is_numeric($rankRaw)) || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }
            $counts[(int) $rankRaw] = (int) $countRaw;
        }

        return $counts;
    }

    /**
     * @return array<int, int>
     */
    public function findLearnedBookCountsByListNumber(PlayerEntity $player): array
    {
        $rows = $this->createQueryBuilder('k')
            ->select('bl.listNumber AS listNumber')
            ->addSelect('COUNT(DISTINCT i.id) AS learnedCount')
            ->join('k.item', 'i')
            ->join('i.bookLists', 'bl')
            ->andWhere('k.player = :player')
            ->andWhere('i.type = :type')
            ->setParameter('player', $player)
            ->setParameter('type', ItemTypeEnum::BOOK)
            ->groupBy('bl.listNumber')
            ->orderBy('bl.listNumber', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $listRaw = $row['listNumber'] ?? null;
            $countRaw = $row['learnedCount'] ?? null;
            if ((!is_int($listRaw) && !is_numeric($listRaw)) || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }
            $counts[(int) $listRaw] = (int) $countRaw;
        }

        return $counts;
    }
}
