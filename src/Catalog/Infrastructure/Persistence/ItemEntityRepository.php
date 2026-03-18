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

use App\Catalog\Application\Admin\AdminCatalogItemReadRepository;
use App\Catalog\Application\Import\ItemImportItemRepository;
use App\Catalog\Application\Import\ItemSourceCollisionReadRepository;
use App\Catalog\Application\Import\ItemSourceComparisonReadRepository;
use App\Catalog\Application\Item\ItemCatalogTimestampReadRepository;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Progression\Application\Knowledge\ItemKnowledgeCatalogReadRepository;
use App\Progression\Application\Knowledge\ItemKnowledgeTransferRepository;
use App\Progression\Application\Knowledge\ItemReadRepository;
use App\Progression\Application\Knowledge\ItemStatsReadRepository;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemEntity>
 */
final class ItemEntityRepository extends ServiceEntityRepository implements ItemKnowledgeTransferRepository, ItemStatsReadRepository, ItemImportItemRepository, ItemKnowledgeCatalogReadRepository, ItemReadRepository, ItemCatalogTimestampReadRepository, ItemSourceComparisonReadRepository, ItemSourceCollisionReadRepository, AdminCatalogItemReadRepository
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
        $value = $this->createQueryBuilder('i')
            ->select('i.updatedAt')
            ->andWhere('i.type = :type')
            ->setParameter('type', $type)
            ->orderBy('i.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!is_array($value) || !($value['updatedAt'] ?? null) instanceof DateTimeImmutable) {
            return null;
        }

        return $value['updatedAt'];
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

    /**
     * @return list<ItemEntity>
     */
    public function findItemsWithProviders(string $providerA, string $providerB, ?ItemTypeEnum $type, int $limit): array
    {
        $qb = $this->createQueryBuilder('i')
            ->addSelect('srcA', 'srcB')
            ->join('i.externalSources', 'srcA', 'WITH', 'srcA.provider = :providerA')
            ->join('i.externalSources', 'srcB', 'WITH', 'srcB.provider = :providerB')
            ->setParameter('providerA', strtolower(trim($providerA)))
            ->setParameter('providerB', strtolower(trim($providerB)))
            ->orderBy('i.type', 'ASC')
            ->addOrderBy('i.sourceId', 'ASC')
            ->setMaxResults(max(1, $limit));

        if (null !== $type) {
            $qb
                ->andWhere('i.type = :type')
                ->setParameter('type', $type);
        }

        $items = $qb->getQuery()->getResult();

        /** @var list<ItemEntity> $items */
        return $items;
    }

    /**
     * @return list<array{
     *     type:string,
     *     externalRef:string,
     *     itemCount:int,
     *     providerCount:int,
     *     providers:list<string>,
     *     sourceIds:list<int>
     * }>
     */
    public function findExternalRefCollisions(string $providerA, string $providerB, ?ItemTypeEnum $type, int $limit): array
    {
        $sql = <<<'SQL'
                SELECT
                    i.type AS type,
                    ies.external_ref AS external_ref,
                    COUNT(DISTINCT i.id) AS item_count,
                    COUNT(DISTINCT ies.provider) AS provider_count,
                    ARRAY_AGG(DISTINCT ies.provider ORDER BY ies.provider) AS providers,
                    ARRAY_AGG(DISTINCT i.source_id ORDER BY i.source_id) AS source_ids
                FROM item_external_source ies
                INNER JOIN item i ON i.id = ies.item_id
                WHERE ies.provider IN (:providers)
            SQL;

        $params = [
            'providers' => [strtolower(trim($providerA)), strtolower(trim($providerB))],
        ];
        $types = [
            'providers' => \Doctrine\DBAL\ArrayParameterType::STRING,
        ];

        if (null !== $type) {
            $sql .= ' AND i.type = :type';
            $params['type'] = $type->value;
            $types['type'] = \Doctrine\DBAL\ParameterType::STRING;
        }

        $sql .= <<<'SQL'
                 GROUP BY i.type, ies.external_ref
                HAVING COUNT(DISTINCT i.id) > 1
                ORDER BY COUNT(DISTINCT i.id) DESC, ies.external_ref ASC
                LIMIT :limit
            SQL;

        $params['limit'] = max(1, $limit);
        $types['limit'] = \Doctrine\DBAL\ParameterType::INTEGER;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $typeValue = $row['type'] ?? null;
            $externalRef = $row['external_ref'] ?? null;
            $itemCount = $row['item_count'] ?? null;
            $providerCount = $row['provider_count'] ?? null;

            if (!is_string($typeValue) || !is_string($externalRef) || !is_scalar($itemCount) || !is_scalar($providerCount) || !is_numeric((string) $itemCount) || !is_numeric((string) $providerCount)) {
                continue;
            }

            $providers = $this->normalizePgArrayStrings($row['providers'] ?? null);
            $sourceIds = array_map('intval', $this->normalizePgArrayStrings($row['source_ids'] ?? null));

            $result[] = [
                'type' => $typeValue,
                'externalRef' => $externalRef,
                'itemCount' => (int) $itemCount,
                'providerCount' => (int) $providerCount,
                'providers' => $providers,
                'sourceIds' => $sourceIds,
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function normalizePgArrayStrings(mixed $value): array
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $entry) {
                if (!is_scalar($entry)) {
                    continue;
                }

                $normalized[] = (string) $entry;
            }

            return $normalized;
        }

        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value, '{}');
        if ('' === $trimmed) {
            return [];
        }

        return array_map(
            static fn (string $entry): string => trim($entry, '"'),
            explode(',', $trimmed),
        );
    }

    public function findOneByPublicId(string $publicId): ?ItemEntity
    {
        $item = $this->findOneBy(['publicId' => $publicId]);

        return $item instanceof ItemEntity ? $item : null;
    }

    public function countByAdminQuery(?ItemTypeEnum $type, ?string $query): int
    {
        $count = $this->createAdminCatalogQueryBuilder($type, $query)
            ->select('COUNT(DISTINCT i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return list<ItemEntity>
     */
    public function findByAdminQuery(?ItemTypeEnum $type, ?string $query, int $page, int $perPage): array
    {
        $idRows = $this->createAdminCatalogQueryBuilder($type, $query)
            ->select('i.id AS id', 'i.type AS sortType', 'i.sourceId AS sortSourceId')
            ->orderBy('sortType', 'ASC')
            ->addOrderBy('sortSourceId', 'ASC')
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults(max(1, $perPage))
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($idRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $idRaw = $row['id'] ?? null;
            if (!is_scalar($idRaw) || !is_numeric((string) $idRaw)) {
                continue;
            }

            $ids[] = (int) $idRaw;
        }

        if ([] === $ids) {
            return [];
        }

        $items = $this->createQueryBuilder('i')
            ->addSelect('src')
            ->leftJoin('i.externalSources', 'src')
            ->andWhere('i.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        if (!is_array($items)) {
            return [];
        }

        $byId = [];
        foreach ($items as $item) {
            if (!$item instanceof ItemEntity || null === $item->getId()) {
                continue;
            }

            $byId[$item->getId()] = $item;
        }

        $orderedItems = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $orderedItems[] = $byId[$id];
            }
        }

        /** @var list<ItemEntity> $orderedItems */
        return $orderedItems;
    }

    public function findOneDetailedByPublicId(string $publicId): ?ItemEntity
    {
        $item = $this->createQueryBuilder('i')
            ->addSelect('src', 'bl')
            ->leftJoin('i.externalSources', 'src')
            ->leftJoin('i.bookLists', 'bl')
            ->andWhere('i.publicId = :publicId')
            ->setParameter('publicId', trim($publicId))
            ->getQuery()
            ->getOneOrNullResult();

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

    private function createAdminCatalogQueryBuilder(?ItemTypeEnum $type, ?string $query): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->distinct();

        if (null !== $type) {
            $qb->andWhere('i.type = :type')
                ->setParameter('type', $type);
        }

        $normalizedQuery = is_string($query) ? trim(mb_strtolower($query)) : '';
        if ('' === $normalizedQuery) {
            return $qb;
        }

        $conditions = [
            'LOWER(i.publicId) LIKE :query',
            'LOWER(i.nameKey) LIKE :query',
            'LOWER(src_search.provider) LIKE :query',
            'LOWER(src_search.externalRef) LIKE :query',
        ];

        if (ctype_digit($normalizedQuery)) {
            $conditions[] = 'i.sourceId = :sourceIdExact';
            $qb->setParameter('sourceIdExact', (int) $normalizedQuery);
        }

        $qb->leftJoin('i.externalSources', 'src_search')
            ->andWhere($qb->expr()->orX(...$conditions))
            ->setParameter('query', '%'.$normalizedQuery.'%');

        return $qb;
    }
}
