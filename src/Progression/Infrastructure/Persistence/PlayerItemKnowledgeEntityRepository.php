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
    public function findLearnedItemIdsByPlayer(PlayerEntity $player, ?ItemTypeEnum $type = null): array
    {
        $qb = $this->createQueryBuilder('k')
            ->select('IDENTITY(k.item) AS itemId')
            ->andWhere('k.player = :player')
            ->setParameter('player', $player);

        if ($type instanceof ItemTypeEnum) {
            $qb
                ->join('k.item', 'i')
                ->andWhere('i.type = :type')
                ->setParameter('type', $type);
        }

        $rows = $qb->getQuery()->getScalarResult();

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

    /**
     * @return array{all: int, misc: int, book: int}
     */
    public function countLearnedByPlayerByType(PlayerEntity $player): array
    {
        $rows = $this->createQueryBuilder('k')
            ->select('i.type AS type')
            ->addSelect('COUNT(k.id) AS learnedCount')
            ->join('k.item', 'i')
            ->andWhere('k.player = :player')
            ->setParameter('player', $player)
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
            $countRaw = $row['learnedCount'] ?? null;
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

    public function countLearnedBooksWithListNumber(PlayerEntity $player): int
    {
        $count = $this->createQueryBuilder('k')
            ->select('COUNT(DISTINCT i.id)')
            ->join('k.item', 'i')
            ->join('i.bookLists', 'bl')
            ->andWhere('k.player = :player')
            ->andWhere('i.type = :type')
            ->setParameter('player', $player)
            ->setParameter('type', ItemTypeEnum::BOOK)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return array{plan: int, recipe: int}
     */
    public function findLearnedBookCountsByKind(PlayerEntity $player): array
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
                    COUNT(DISTINCT i.id) AS learned_count
                FROM player_item_knowledge k
                INNER JOIN item i ON i.id = k.item_id
                WHERE k.player_id = :playerId
                  AND i.type = :type
                GROUP BY book_kind
            SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'playerId' => $player->getId(),
            'type' => ItemTypeEnum::BOOK->value,
        ])->fetchAllAssociative();

        $counts = ['plan' => 0, 'recipe' => 0];
        foreach ($rows as $row) {
            $kind = $row['book_kind'] ?? null;
            $countRaw = $row['learned_count'] ?? null;
            if (!is_string($kind) || !array_key_exists($kind, $counts) || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }

            $counts[$kind] = (int) $countRaw;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function findLearnedBookCountsByCategory(PlayerEntity $player): array
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
                    COUNT(DISTINCT i.id) AS learned_count
                FROM player_item_knowledge k
                INNER JOIN item i ON i.id = k.item_id
                WHERE k.player_id = :playerId
                  AND i.type = :type
                GROUP BY book_category
            SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'playerId' => $player->getId(),
            'type' => ItemTypeEnum::BOOK->value,
        ])->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $category = $row['book_category'] ?? null;
            $countRaw = $row['learned_count'] ?? null;
            if (!is_string($category) || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }

            $counts[$category] = (int) $countRaw;
        }

        return $counts;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function findLearnedBookCountsBySubcategory(PlayerEntity $player): array
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
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%weapon%') AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%ballistic%') THEN 'ballistic'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND (LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%' OR LOWER(COALESCE(ies.metadata->>'source_page_url', '')) LIKE '%weapon%') AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%melee%') THEN 'melee'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%plans_weapons%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%thrown%') THEN 'thrown'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%bows%') THEN 'bows'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%alien%') THEN 'alien'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%camera%') THEN 'camera'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%weapon%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%unused%') THEN 'unused'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%apparel%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%headwear%') THEN 'headwear'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%apparel%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%backpacks%') THEN 'backpacks'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%apparel%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%outfits%') THEN 'outfits'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%arctic marine armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%arctic marine armor%'))) THEN 'arctic_marine'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%botsmith armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%botsmith armor%'))) THEN 'botsmith'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%brotherhood recon armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%brotherhood recon armor%'))) THEN 'brotherhood_recon'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%chinese stealth armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%chinese stealth armor%'))) THEN 'chinese_stealth'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%combat armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%combat armor%'))) THEN 'combat'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%covert scout armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%covert scout armor%'))) THEN 'covert_scout'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%leather armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%leather armor%'))) THEN 'leather'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%marine armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%marine armor%'))) THEN 'marine'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%metal armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%metal armor%'))) THEN 'metal'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE '%muni armor%' OR LOWER(COALESCE(ies.metadata->>'name_en', '')) LIKE '%muni underarmor%' OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE '%muni armor%' OR LOWER(COALESCE(ies.metadata->>'name', '')) LIKE '%muni underarmor%' OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE '%muni_armor%' OR LOWER(COALESCE(ies.metadata->>'source_slug', '')) LIKE '%muni_underarmor%')) THEN 'muni'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%pip-boy%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%pip-boy%'))) THEN 'pip_boy'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%raider armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%raider armor%'))) THEN 'raider'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%robot armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%robot armor%'))) THEN 'robot'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%secret service armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%secret service armor%'))) THEN 'secret_service'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND ((LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%solar armor%' OR LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%thorn armor%') OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%solar armor%' OR LOWER(section.value) LIKE '%thorn armor%'))) THEN 'solar_thorn'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%trapper armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%trapper armor%'))) THEN 'trapper'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%underarmor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%underarmor%'))) THEN 'underarmor'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%armor%' AND (LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%wood armor%' OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(ies.metadata->'source_sections', '[]'::jsonb)) AS section(value) WHERE LOWER(section.value) LIKE '%wood armor%'))) THEN 'wood'
                        WHEN EXISTS (SELECT 1 FROM item_external_source ies WHERE ies.item_id = i.id AND LOWER(COALESCE(ies.metadata->>'source_page', '')) LIKE '%power_armor%' AND LOWER(COALESCE(ies.metadata->>'source_section', '')) LIKE '%strangler heart%') THEN 'strangler_heart'
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
                    COUNT(DISTINCT i.id) AS learned_count
                FROM player_item_knowledge k
                INNER JOIN item i ON i.id = k.item_id
                WHERE k.player_id = :playerId
                  AND i.type = :type
                GROUP BY book_category, book_subcategory
            SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'playerId' => $player->getId(),
            'type' => ItemTypeEnum::BOOK->value,
        ])->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $category = $row['book_category'] ?? null;
            $subcategory = $row['book_subcategory'] ?? null;
            $countRaw = $row['learned_count'] ?? null;
            if (!is_string($category) || !is_string($subcategory) || '' === $subcategory || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }

            $counts[$category][$subcategory] = (int) $countRaw;
        }

        return $counts;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function findLearnedBookCountsByDetail(PlayerEntity $player): array
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
                    COUNT(DISTINCT i.id) AS learned_count
                FROM player_item_knowledge k
                INNER JOIN item i ON i.id = k.item_id
                WHERE k.player_id = :playerId
                  AND i.type = :type
                GROUP BY book_category, book_detail
            SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'playerId' => $player->getId(),
            'type' => ItemTypeEnum::BOOK->value,
        ])->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $category = $row['book_category'] ?? null;
            $detail = $row['book_detail'] ?? null;
            $countRaw = $row['learned_count'] ?? null;
            if (!is_string($category) || !is_string($detail) || '' === $detail || (!is_int($countRaw) && !is_numeric($countRaw))) {
                continue;
            }

            $counts[$category][$detail] = (int) $countRaw;
        }

        return $counts;
    }
}
