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

use App\Progression\Domain\Entity\PlayerEntity;

final class PlayerKnowledgeStatsApplicationService
{
    private const BOOK_CATEGORY_ORDER = [
        'weapon_plan',
        'weapon_mod_plan',
        'armor_plan',
        'apparel_plan',
        'armor_mod_plan',
        'power_armor_plan',
        'power_armor_mod_plan',
        'workshop_plan',
        'recipe',
    ];
    /**
     * @var array<string, array<string, string>>
     */
    private const BOOK_SUBCATEGORY_LABELS = [
        'weapon_plan' => [
            'ballistic' => 'Ballistic',
            'melee' => 'Melee',
            'thrown' => 'Thrown',
            'bows' => 'Bows',
            'alien' => 'Alien',
            'camera' => 'Camera',
            'unused' => 'Unused',
        ],
        'weapon_mod_plan' => [
            'ballistic' => 'Ballistic',
            'melee' => 'Melee',
            'bows' => 'Bows',
            'alien' => 'Alien',
            'camera' => 'Camera',
            'unused' => 'Unused',
        ],
        'armor_plan' => [
            'arctic_marine' => 'Arctic Marine',
            'botsmith' => 'Botsmith',
            'brotherhood_recon' => 'Brotherhood Recon',
            'chinese_stealth' => 'Chinese Stealth',
            'combat' => 'Combat',
            'covert_scout' => 'Covert Scout',
            'leather' => 'Leather',
            'marine' => 'Marine',
            'metal' => 'Metal',
            'raider' => 'Raider',
            'robot' => 'Robot',
            'secret_service' => 'Secret Service',
            'solar_thorn' => 'Solar / Thorn',
            'trapper' => 'Trapper',
        ],
        'apparel_plan' => [
            'outfits' => 'Outfits',
            'headwear' => 'Headwear',
            'backpacks' => 'Backpacks',
        ],
        'armor_mod_plan' => [
            'brotherhood_recon' => 'Brotherhood Recon',
            'combat' => 'Combat',
            'leather' => 'Leather',
            'marine' => 'Marine',
            'metal' => 'Metal',
            'pip_boy' => 'Pip-Boy',
            'raider' => 'Raider',
            'robot' => 'Robot',
            'secret_service' => 'Secret Service',
            'trapper' => 'Trapper',
            'underarmor' => 'Underarmor',
            'wood' => 'Wood',
        ],
        'power_armor_plan' => [
            'union' => 'Union',
            'vulcan' => 'Vulcan',
            'hellcat' => 'Hellcat',
            'excavator' => 'Excavator',
            'raider' => 'Raider',
            'strangler_heart' => 'Strangler Heart',
            't_45' => 'T-45',
            't_51' => 'T-51',
            't_60' => 'T-60',
            't_65' => 'T-65',
            'ultracite' => 'Ultracite',
            'x_01' => 'X-01',
        ],
        'power_armor_mod_plan' => [
            'union' => 'Union',
            'vulcan' => 'Vulcan',
            'hellcat' => 'Hellcat',
            'excavator' => 'Excavator',
            'raider' => 'Raider',
            'strangler_heart' => 'Strangler Heart',
            't_45' => 'T-45',
            't_51' => 'T-51',
            't_60' => 'T-60',
            't_65' => 'T-65',
            'ultracite' => 'Ultracite',
            'x_01' => 'X-01',
            'unused' => 'Unused',
        ],
        'workshop_plan' => [
            'floor_decor' => 'Floor Decor',
            'wall_decor' => 'Wall Decor',
            'lights' => 'Lights',
            'utility' => 'Utility',
            'structures' => 'Structures',
            'display' => 'Display',
            'allies' => 'Allies',
            'crafting' => 'Crafting',
            'defenses' => 'Defenses',
        ],
    ];
    /**
     * @var array<string, array<string, string>>
     */
    private const BOOK_DETAIL_LABELS = [
        'recipe' => [
            'brewing' => 'Brewing',
            'chems' => 'Chems',
            'cooking_drinks' => 'Cooking (Drinks)',
            'cooking_food' => 'Cooking (Food)',
            'junk' => 'Junk',
            'serums' => 'Serums',
        ],
        'workshop_plan' => [
            'appliances' => 'Appliances',
            'beds' => 'Beds',
            'chairs' => 'Chairs',
            'crafting' => 'Crafting',
            'defenses' => 'Defenses',
            'displays' => 'Displays',
            'doors' => 'Doors',
            'floor_decor' => 'Floor Decor',
            'floors' => 'Floors',
            'food' => 'Food',
            'generators' => 'Generators',
            'lights' => 'Lights',
            'misc_structures' => 'Misc. Structures',
            'power_connectors' => 'Power Connectors',
            'resources' => 'Resources',
            'shelves' => 'Shelves',
            'stash_boxes' => 'Stash Boxes',
            'tables' => 'Tables',
            'turrets_traps' => 'Turrets & Traps',
            'vendors' => 'Vendors',
            'wall_decor' => 'Wall Decor',
            'walls' => 'Walls',
            'water' => 'Water',
        ],
    ];

    public function __construct(
        private readonly ItemStatsReadRepository $itemRepository,
        private readonly PlayerKnowledgeStatsReadRepository $knowledgeRepository,
    ) {
    }

    /**
     * @return array{
     *     playerId: string,
     *     overall: array{learned: int, total: int, percent: int},
     *     byType: array{
     *         misc: array{learned: int, total: int, percent: int},
     *         book: array{learned: int, total: int, percent: int}
     *     },
     *     minervaBooks: array{learned: int, total: int, percent: int},
     *     byBookKind: array{
     *         plan: array{learned: int, total: int, percent: int},
     *         recipe: array{learned: int, total: int, percent: int}
     *     },
     *     bookByCategory: list<array{category: string, learned: int, total: int, percent: int}>,
     *     bookBySubcategory: list<array{category: string, subcategory: string, label: string, learned: int, total: int, percent: int}>,
     *     bookByDetail: list<array{category: string, detail: string, label: string, learned: int, total: int, percent: int}>,
     *     miscByRank: list<array{rank: int, learned: int, total: int, percent: int}>,
     *     bookByList: list<array{listNumber: int, learned: int, total: int, percent: int}>
     * }
     */
    public function getStats(PlayerEntity $player): array
    {
        $totals = $this->itemRepository->countAllByType();
        $totalAll = $totals['all'];
        $totalMisc = $totals['misc'];
        $totalBook = $totals['book'];

        $learned = $this->knowledgeRepository->countLearnedByPlayerByType($player);
        $learnedAll = $learned['all'];
        $learnedMisc = $learned['misc'];
        $learnedBook = $learned['book'];

        $miscTotals = $this->itemRepository->findMiscTotalsByRank();
        $miscLearned = $this->knowledgeRepository->findLearnedMiscCountsByRank($player);
        $bookTotals = $this->itemRepository->findBookTotalsByListNumber();
        $bookLearned = $this->knowledgeRepository->findLearnedBookCountsByListNumber($player);
        $listedBookTotal = $this->itemRepository->countBooksWithListNumber();
        $listedBookLearned = $this->knowledgeRepository->countLearnedBooksWithListNumber($player);
        $bookTotalsByKind = $this->itemRepository->findBookTotalsByKind();
        $bookLearnedByKind = $this->knowledgeRepository->findLearnedBookCountsByKind($player);
        $bookTotalsByCategory = $this->itemRepository->findBookTotalsByCategory();
        $bookLearnedByCategory = $this->knowledgeRepository->findLearnedBookCountsByCategory($player);
        $bookTotalsBySubcategory = $this->itemRepository->findBookTotalsBySubcategory();
        $bookLearnedBySubcategory = $this->knowledgeRepository->findLearnedBookCountsBySubcategory($player);
        $bookTotalsByDetail = $this->itemRepository->findBookTotalsByDetail();
        $bookLearnedByDetail = $this->knowledgeRepository->findLearnedBookCountsByDetail($player);

        $miscByRank = [];
        foreach ($miscTotals as $rank => $total) {
            $learned = $miscLearned[$rank] ?? 0;
            $miscByRank[] = [
                'rank' => $rank,
                'learned' => $learned,
                'total' => $total,
                'percent' => $this->toPercent($learned, $total),
            ];
        }

        $bookByList = [];
        foreach ($bookTotals as $listNumber => $total) {
            $learned = $bookLearned[$listNumber] ?? 0;
            $bookByList[] = [
                'listNumber' => $listNumber,
                'learned' => $learned,
                'total' => $total,
                'percent' => $this->toPercent($learned, $total),
            ];
        }

        $bookByCategory = [];
        foreach (self::BOOK_CATEGORY_ORDER as $category) {
            $total = $bookTotalsByCategory[$category] ?? 0;
            $learned = $bookLearnedByCategory[$category] ?? 0;
            if (0 === $total && 0 === $learned) {
                continue;
            }

            $bookByCategory[] = [
                'category' => $category,
                'learned' => $learned,
                'total' => $total,
                'percent' => $this->toPercent($learned, $total),
            ];
        }

        $bookBySubcategory = [];
        foreach (self::BOOK_SUBCATEGORY_LABELS as $category => $subcategories) {
            foreach ($subcategories as $subcategory => $label) {
                $total = $bookTotalsBySubcategory[$category][$subcategory] ?? 0;
                $learned = $bookLearnedBySubcategory[$category][$subcategory] ?? 0;
                if (0 === $total && 0 === $learned) {
                    continue;
                }

                $bookBySubcategory[] = [
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'label' => $label,
                    'learned' => $learned,
                    'total' => $total,
                    'percent' => $this->toPercent($learned, $total),
                ];
            }
        }

        $bookByDetail = [];
        foreach (self::BOOK_DETAIL_LABELS as $category => $details) {
            foreach ($details as $detail => $label) {
                $total = $bookTotalsByDetail[$category][$detail] ?? 0;
                $learned = $bookLearnedByDetail[$category][$detail] ?? 0;
                if (0 === $total && 0 === $learned) {
                    continue;
                }

                $bookByDetail[] = [
                    'category' => $category,
                    'detail' => $detail,
                    'label' => $label,
                    'learned' => $learned,
                    'total' => $total,
                    'percent' => $this->toPercent($learned, $total),
                ];
            }
        }

        return [
            'playerId' => $player->getPublicId(),
            'overall' => [
                'learned' => $learnedAll,
                'total' => $totalAll,
                'percent' => $this->toPercent($learnedAll, $totalAll),
            ],
            'byType' => [
                'misc' => [
                    'learned' => $learnedMisc,
                    'total' => $totalMisc,
                    'percent' => $this->toPercent($learnedMisc, $totalMisc),
                ],
                'book' => [
                    'learned' => $learnedBook,
                    'total' => $totalBook,
                    'percent' => $this->toPercent($learnedBook, $totalBook),
                ],
            ],
            'minervaBooks' => [
                'learned' => $listedBookLearned,
                'total' => $listedBookTotal,
                'percent' => $this->toPercent($listedBookLearned, $listedBookTotal),
            ],
            'byBookKind' => [
                'plan' => [
                    'learned' => $bookLearnedByKind['plan'],
                    'total' => $bookTotalsByKind['plan'],
                    'percent' => $this->toPercent($bookLearnedByKind['plan'], $bookTotalsByKind['plan']),
                ],
                'recipe' => [
                    'learned' => $bookLearnedByKind['recipe'],
                    'total' => $bookTotalsByKind['recipe'],
                    'percent' => $this->toPercent($bookLearnedByKind['recipe'], $bookTotalsByKind['recipe']),
                ],
            ],
            'bookByCategory' => $bookByCategory,
            'bookBySubcategory' => $bookBySubcategory,
            'bookByDetail' => $bookByDetail,
            'miscByRank' => $miscByRank,
            'bookByList' => $bookByList,
        ];
    }

    private function toPercent(int $learned, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) round(($learned / $total) * 100);
    }
}
