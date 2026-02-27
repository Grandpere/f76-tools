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

use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Entity\PlayerEntity;

final class PlayerKnowledgeStatsApplicationService
{
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
     *     miscByRank: list<array{rank: int, learned: int, total: int, percent: int}>,
     *     bookByList: list<array{listNumber: int, learned: int, total: int, percent: int}>
     * }
     */
    public function getStats(PlayerEntity $player): array
    {
        $totalAll = $this->itemRepository->countAll();
        $totalMisc = $this->itemRepository->countByType(ItemTypeEnum::MISC);
        $totalBook = $this->itemRepository->countByType(ItemTypeEnum::BOOK);

        $learnedAll = $this->knowledgeRepository->countLearnedByPlayer($player);
        $learnedMisc = $this->knowledgeRepository->countLearnedByPlayerAndType($player, ItemTypeEnum::MISC);
        $learnedBook = $this->knowledgeRepository->countLearnedByPlayerAndType($player, ItemTypeEnum::BOOK);

        $miscTotals = $this->itemRepository->findMiscTotalsByRank();
        $miscLearned = $this->knowledgeRepository->findLearnedMiscCountsByRank($player);
        $bookTotals = $this->itemRepository->findBookTotalsByListNumber();
        $bookLearned = $this->knowledgeRepository->findLearnedBookCountsByListNumber($player);

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
