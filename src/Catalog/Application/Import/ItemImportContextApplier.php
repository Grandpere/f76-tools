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

namespace App\Catalog\Application\Import;

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;

final class ItemImportContextApplier
{
    /**
     * @param array{type: ItemTypeEnum, rank: int|null, listNumber: int|null, isSpecialList: bool} $context
     *
     * @return array{valid: bool, warning: string|null}
     */
    public function apply(ItemEntity $item, int $sourceId, array $context): array
    {
        $type = $context['type'];

        if (ItemTypeEnum::MISC === $type) {
            $incomingRank = $context['rank'];
            if (null === $incomingRank) {
                return ['valid' => false, 'warning' => null];
            }

            if (null !== $item->getRank() && $item->getRank() !== $incomingRank) {
                return [
                    'valid' => true,
                    'warning' => sprintf(
                        'Conflit rank pour MISC id=%d (%d -> %d), conservation=%d',
                        $sourceId,
                        $item->getRank(),
                        $incomingRank,
                        $item->getRank(),
                    ),
                ];
            }

            $item->setRank($incomingRank);

            return ['valid' => true, 'warning' => null];
        }

        $incomingListNumber = $context['listNumber'];
        $incomingIsSpecial = $context['isSpecialList'];
        if (null === $incomingListNumber) {
            return ['valid' => false, 'warning' => null];
        }

        $item->setRank(null);
        $item->addBookList($incomingListNumber, $incomingIsSpecial);

        return ['valid' => true, 'warning' => null];
    }
}
