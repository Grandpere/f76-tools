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

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemEntity>
 */
final class ItemEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemEntity::class);
    }

    public function findOneByTypeAndSourceId(ItemTypeEnum $type, int $sourceId): ?ItemEntity
    {
        return $this->findOneBy([
            'type' => $type,
            'sourceId' => $sourceId,
        ]);
    }

    public function findOneById(int $id): ?ItemEntity
    {
        $item = $this->find($id);

        return $item instanceof ItemEntity ? $item : null;
    }

    public function countAll(): int
    {
        $count = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function countByType(ItemTypeEnum $type): int
    {
        $count = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return array<int, int>
     */
    public function findMiscTotalsByRank(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.rank AS rank')
            ->addSelect('COUNT(i.id) AS totalCount')
            ->andWhere('i.type = :type')
            ->setParameter('type', ItemTypeEnum::MISC)
            ->groupBy('i.rank')
            ->orderBy('i.rank', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $totals = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rankRaw = $row['rank'] ?? null;
            $countRaw = $row['totalCount'] ?? null;
            if ((!is_int($rankRaw) && !is_numeric($rankRaw)) || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }
            $totals[(int) $rankRaw] = (int) $countRaw;
        }

        return $totals;
    }

    /**
     * @return array<int, int>
     */
    public function findBookTotalsByListNumber(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('bl.listNumber AS listNumber')
            ->addSelect('COUNT(DISTINCT i.id) AS totalCount')
            ->join('i.bookLists', 'bl')
            ->andWhere('i.type = :type')
            ->setParameter('type', ItemTypeEnum::BOOK)
            ->groupBy('bl.listNumber')
            ->orderBy('bl.listNumber', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $totals = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $listRaw = $row['listNumber'] ?? null;
            $countRaw = $row['totalCount'] ?? null;
            if ((!is_int($listRaw) && !is_numeric($listRaw)) || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }
            $totals[(int) $listRaw] = (int) $countRaw;
        }

        return $totals;
    }

    /**
     * @return list<ItemEntity>
     */
    public function findAllOrdered(?ItemTypeEnum $type = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.type', 'ASC')
            ->addOrderBy('i.sourceId', 'ASC');

        if (null !== $type) {
            $qb->andWhere('i.type = :type')
                ->setParameter('type', $type);
        }

        $items = $qb->getQuery()->getResult();

        /** @var list<ItemEntity> $items */
        return $items;
    }
}
