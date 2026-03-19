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
        'armor_mod_plan',
        'power_armor_plan',
        'power_armor_mod_plan',
        'workshop_plan',
        'recipe',
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
