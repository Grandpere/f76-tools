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
use App\Catalog\Application\Item\BookCatalogFrontReadRepository;
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
final class ItemEntityRepository extends ServiceEntityRepository implements ItemKnowledgeTransferRepository, ItemStatsReadRepository, ItemImportItemRepository, ItemKnowledgeCatalogReadRepository, ItemReadRepository, ItemCatalogTimestampReadRepository, ItemSourceComparisonReadRepository, ItemSourceCollisionReadRepository, AdminCatalogItemReadRepository, BookCatalogFrontReadRepository
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

    public function findBooksByExternalRef(string $externalRef): array
    {
        $normalizedRef = strtoupper(trim($externalRef));
        if ('' === $normalizedRef) {
            return [];
        }

        $items = $this->createQueryBuilder('i')
            ->addSelect('src')
            ->leftJoin('i.externalSources', 'src')
            ->andWhere('i.type = :type')
            ->andWhere('EXISTS (
                SELECT 1
                FROM App\\Catalog\\Domain\\Entity\\ItemExternalSourceEntity src_match
                WHERE src_match.item = i
                  AND UPPER(src_match.externalRef) = :externalRef
            )')
            ->setParameter('type', ItemTypeEnum::BOOK)
            ->setParameter('externalRef', $normalizedRef)
            ->orderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var list<ItemEntity> $items */
        return $items;
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

    /**
     * @return list<ItemEntity>
     */
    public function findAllDetailedByAdminQuery(?ItemTypeEnum $type, ?string $query): array
    {
        $idRows = $this->createAdminCatalogQueryBuilder($type, $query)
            ->select('i.id AS id', 'i.type AS sortType', 'i.sourceId AS sortSourceId')
            ->orderBy('sortType', 'ASC')
            ->addOrderBy('sortSourceId', 'ASC')
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

    public function countBooksWithListNumber(): int
    {
        $count = $this->createQueryBuilder('i')
            ->select('COUNT(DISTINCT i.id)')
            ->join('i.bookLists', 'bl')
            ->andWhere('i.type = :type')
            ->setParameter('type', ItemTypeEnum::BOOK)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return array{plan: int, recipe: int}
     */
    public function findBookTotalsByKind(): array
    {
        $sql = <<<'SQL'
                SELECT
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                        ) THEN 'recipe'
                        ELSE 'plan'
                    END AS book_kind,
                    COUNT(*) AS total_count
                FROM item i
                WHERE i.type = :type
                GROUP BY book_kind
            SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'type' => ItemTypeEnum::BOOK->value,
        ])->fetchAllAssociative();

        $totals = ['plan' => 0, 'recipe' => 0];
        foreach ($rows as $row) {
            $kind = $row['book_kind'] ?? null;
            $countRaw = $row['total_count'] ?? null;
            if (!is_string($kind) || !array_key_exists($kind, $totals) || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }

            $totals[$kind] = (int) $countRaw;
        }

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    public function findBookTotalsByCategory(): array
    {
        $sql = <<<'SQL'
                SELECT
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                        ) THEN 'recipe'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor_mod%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%power_armor_mod%'
                              )
                        ) THEN 'power_armor_mod_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%power_armor%'
                              )
                        ) THEN 'power_armor_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon_mod%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%weapon_mod%'
                              )
                        ) THEN 'weapon_mod_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%weapon%'
                              )
                        ) THEN 'weapon_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%apparel%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%apparel%'
                              )
                        ) THEN 'apparel_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor_mod%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%armor_mod%'
                              )
                        ) THEN 'armor_mod_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%armor%'
                              )
                        ) THEN 'armor_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%'
                              )
                        ) THEN 'workshop_plan'
                        ELSE 'plan'
                    END AS book_category,
                    COUNT(*) AS total_count
                FROM item i
                WHERE i.type = :type
                GROUP BY book_category
            SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'type' => ItemTypeEnum::BOOK->value,
        ])->fetchAllAssociative();

        $totals = [];
        foreach ($rows as $row) {
            $category = $row['book_category'] ?? null;
            $countRaw = $row['total_count'] ?? null;
            if (!is_string($category) || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }

            $totals[$category] = (int) $countRaw;
        }

        return $totals;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function findBookTotalsBySubcategory(): array
    {
        $sql = <<<'SQL'
                SELECT
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                        ) THEN 'recipe'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor_mod%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%power_armor_mod%'
                              )
                        ) THEN 'power_armor_mod_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%power_armor%'
                              )
                        ) THEN 'power_armor_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon_mod%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%weapon_mod%'
                              )
                        ) THEN 'weapon_mod_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%weapon%'
                              )
                        ) THEN 'weapon_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%apparel%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%apparel%'
                              )
                        ) THEN 'apparel_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor_mod%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%armor_mod%'
                              )
                        ) THEN 'armor_mod_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%armor%'
                              )
                        ) THEN 'armor_plan'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%'
                              )
                        ) THEN 'workshop_plan'
                        ELSE 'plan'
                    END AS book_category,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%weapon%'
                              )
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%ballistic%'
                        ) THEN 'ballistic'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%weapon%'
                              )
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%melee%'
                        ) THEN 'melee'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%plans_weapons%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%thrown%'
                        ) THEN 'thrown'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%bows%'
                        ) THEN 'bows'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%alien%'
                        ) THEN 'alien'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%camera%'
                        ) THEN 'camera'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%unused%'
                        ) THEN 'unused'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%apparel%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%headwear%'
                        ) THEN 'headwear'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%apparel%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%backpacks%'
                        ) THEN 'backpacks'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%apparel%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%outfits%'
                        ) THEN 'outfits'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%arctic marine armor%'
                        ) THEN 'arctic_marine'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%botsmith armor%'
                        ) THEN 'botsmith'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%brotherhood recon armor%'
                        ) THEN 'brotherhood_recon'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%chinese stealth armor%'
                        ) THEN 'chinese_stealth'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%combat armor%'
                        ) THEN 'combat'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%covert scout armor%'
                        ) THEN 'covert_scout'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%leather armor%'
                        ) THEN 'leather'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%marine armor%'
                        ) THEN 'marine'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%metal armor%'
                        ) THEN 'metal'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%pip-boy%'
                        ) THEN 'pip_boy'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%raider armor%'
                        ) THEN 'raider'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%robot armor%'
                        ) THEN 'robot'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%secret service armor%'
                        ) THEN 'secret_service'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%solar armor%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%thorn armor%'
                              )
                        ) THEN 'solar_thorn'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%trapper armor%'
                        ) THEN 'trapper'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%underarmor%'
                        ) THEN 'underarmor'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%wood armor%'
                        ) THEN 'wood'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%'
                              AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%strangler heart%'
                        ) THEN 'strangler_heart'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%excavator%') THEN 'excavator'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%raider%') THEN 'raider'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%t-45%') THEN 't_45'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%t-51%') THEN 't_51'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%t-60%') THEN 't_60'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%t-65%') THEN 't_65'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%ultracite%') THEN 'ultracite'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%union%') THEN 'union'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%hellcat%') THEN 'hellcat'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%x-01%') THEN 'x_01'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%vulcan%') THEN 'vulcan'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%floor decor%') THEN 'floor_decor'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%wall decor%') THEN 'wall_decor'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%lights%') THEN 'lights'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%utility%') THEN 'utility'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%structures%') THEN 'structures'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%display%') THEN 'display'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%allies%') THEN 'allies'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%crafting%') THEN 'crafting'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%defenses%') THEN 'defenses'
                        ELSE NULL
                    END AS book_subcategory,
                    COUNT(*) AS total_count
                FROM item i
                WHERE i.type = :type
                GROUP BY book_category, book_subcategory
            SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'type' => ItemTypeEnum::BOOK->value,
        ])->fetchAllAssociative();

        $totals = [];
        foreach ($rows as $row) {
            $category = $row['book_category'] ?? null;
            $subcategory = $row['book_subcategory'] ?? null;
            $countRaw = $row['total_count'] ?? null;
            if (!is_string($category) || !is_string($subcategory) || '' === $subcategory || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }

            $totals[$category][$subcategory] = (int) $countRaw;
        }

        return $totals;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function findBookTotalsByDetail(): array
    {
        $sql = <<<'SQL'
                SELECT
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                        ) THEN 'recipe'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%'
                              )
                        ) THEN 'workshop_plan'
                        ELSE NULL
                    END AS book_category,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value)
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                              AND LOWER(section.value) LIKE '%brewing%'
                        ) THEN 'brewing'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value)
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                              AND LOWER(section.value) LIKE '%chems%'
                        ) THEN 'chems'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value)
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                              AND LOWER(section.value) LIKE '%cooking (drinks)%'
                        ) THEN 'cooking_drinks'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value)
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                              AND LOWER(section.value) LIKE '%cooking (food)%'
                        ) THEN 'cooking_food'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value)
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                              AND LOWER(section.value) LIKE '%junk%'
                        ) THEN 'junk'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value)
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'source_item_type', '')) = 'recipe'
                                  OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE 'recipe:%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE 'recipe:%'
                              )
                              AND LOWER(section.value) LIKE '%serums%'
                        ) THEN 'serums'
                        WHEN EXISTS (
                            SELECT 1
                            FROM item_external_source ies
                            CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value)
                            WHERE ies.item_id = i.id
                              AND (
                                  LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%'
                                  OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%'
                              )
                              AND LOWER(section.value) LIKE '%appliances%'
                        ) THEN 'appliances'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%beds%') THEN 'beds'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%chairs%') THEN 'chairs'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%crafting%') THEN 'crafting'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%defenses%') THEN 'defenses'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%displays%') THEN 'displays'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%doors%') THEN 'doors'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%floor decor%') THEN 'floor_decor'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%floors%') THEN 'floors'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%food%') THEN 'food'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%generators%') THEN 'generators'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%lights%') THEN 'lights'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%misc. structures%') THEN 'misc_structures'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%power connectors%') THEN 'power_connectors'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%resources%') THEN 'resources'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%shelves%') THEN 'shelves'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%stash boxes%') THEN 'stash_boxes'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%tables%') THEN 'tables'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%turrets &amp; traps%') THEN 'turrets_traps'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%vendors%') THEN 'vendors'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%wall decor%') THEN 'wall_decor'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%walls%') THEN 'walls'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%workshop%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%workshop%') AND LOWER(section.value) LIKE '%water%') THEN 'water'
                        ELSE NULL
                    END AS book_detail,
                    COUNT(*) AS total_count
                FROM item i
                WHERE i.type = :type
                GROUP BY book_category, book_detail
            SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'type' => ItemTypeEnum::BOOK->value,
        ])->fetchAllAssociative();

        $totals = [];
        foreach ($rows as $row) {
            $category = $row['book_category'] ?? null;
            $detail = $row['book_detail'] ?? null;
            $countRaw = $row['total_count'] ?? null;
            if (!is_string($category) || !is_string($detail) || '' === $detail || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }

            $totals[$category][$detail] = (int) $countRaw;
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

    /**
     * @return list<ItemEntity>
     */
    public function findAllBooksDetailedOrdered(): array
    {
        $items = $this->createQueryBuilder('i')
            ->leftJoin('i.bookLists', 'bl')
            ->addSelect('bl')
            ->leftJoin('i.externalSources', 'src')
            ->addSelect('src')
            ->andWhere('i.type = :type')
            ->setParameter('type', ItemTypeEnum::BOOK)
            ->orderBy('i.sourceId', 'ASC')
            ->addOrderBy('bl.listNumber', 'ASC')
            ->addOrderBy('src.provider', 'ASC')
            ->getQuery()
            ->getResult();

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
