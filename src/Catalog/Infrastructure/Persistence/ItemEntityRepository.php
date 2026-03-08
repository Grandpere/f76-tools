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

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Application\Import\ItemImportItemRepository;
use App\Catalog\Application\Item\ItemCatalogTimestampReadRepository;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Progression\Application\Knowledge\ItemKnowledgeCatalogReadRepository;
use App\Progression\Application\Knowledge\ItemKnowledgeTransferRepository;
use App\Progression\Application\Knowledge\ItemReadRepository;
use App\Progression\Application\Knowledge\ItemStatsReadRepository;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemEntity>
 */
final class ItemEntityRepository extends ServiceEntityRepository implements ItemKnowledgeTransferRepository, ItemStatsReadRepository, ItemImportItemRepository, ItemKnowledgeCatalogReadRepository, ItemReadRepository, ItemCatalogTimestampReadRepository
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

    public function findLatestUpdatedAtByType(ItemTypeEnum $type): ?DateTimeImmutable
    {
        $item = $this->createQueryBuilder('i')
            ->andWhere('i.type = :type')
            ->setParameter('type', $type)
            ->orderBy('i.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$item instanceof ItemEntity) {
            return null;
        }

        return $item->getUpdatedAt();
    }

    public function deleteAllBookLists(): int
    {
        $deleted = $this->getEntityManager()->getConnection()->executeStatement('DELETE FROM item_book_list');

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * @param list<int> $sourceIds
     *
     * @return list<ItemEntity>
     */
    public function findByTypeAndSourceIds(ItemTypeEnum $type, array $sourceIds): array
    {
        if ([] === $sourceIds) {
            return [];
        }

        $items = $this->createQueryBuilder('i')
            ->andWhere('i.type = :type')
            ->andWhere('i.sourceId IN (:sourceIds)')
            ->setParameter('type', $type)
            ->setParameter('sourceIds', $sourceIds)
            ->orderBy('i.sourceId', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var list<ItemEntity> $items */
        return $items;
    }

    public function findOneById(int $id): ?ItemEntity
    {
        $item = $this->find($id);

        return $item instanceof ItemEntity ? $item : null;
    }

    public function findOneByPublicId(string $publicId): ?ItemEntity
    {
        $item = $this->findOneBy(['publicId' => $publicId]);

        return $item instanceof ItemEntity ? $item : null;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<ItemEntity>
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $items = $this->findBy(['id' => $ids]);

        /** @var list<ItemEntity> $items */
        return $items;
    }

    /**
     * @return array{all: int, misc: int, book: int}
     */
    public function countAllByType(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.type AS type')
            ->addSelect('COUNT(i.id) AS totalCount')
            ->groupBy('i.type')
            ->getQuery()
            ->getScalarResult();

        $misc = 0;
        $book = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $typeRaw = $row['type'] ?? null;
            $countRaw = $row['totalCount'] ?? null;
            if (!is_string($typeRaw) || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }

            $count = (int) $countRaw;
            if (ItemTypeEnum::MISC->value === $typeRaw) {
                $misc = $count;
                continue;
            }
            if (ItemTypeEnum::BOOK->value === $typeRaw) {
                $book = $count;
            }
        }

        return [
            'all' => $misc + $book,
            'misc' => $misc,
            'book' => $book,
        ];
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
            ->leftJoin('i.bookLists', 'bl')
            ->addSelect('bl')
            ->orderBy('i.type', 'ASC')
            ->addOrderBy('i.sourceId', 'ASC')
            ->addOrderBy('bl.listNumber', 'ASC');

        if (null !== $type) {
            $qb->andWhere('i.type = :type')
                ->setParameter('type', $type);
        }

        $items = $qb->getQuery()->getResult();

        /** @var list<ItemEntity> $items */
        return $items;
    }
}
