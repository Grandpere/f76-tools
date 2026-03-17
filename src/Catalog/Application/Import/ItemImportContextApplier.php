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

use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;

final class ItemImportContextApplier
{
    public function apply(ItemEntity $item, int $sourceId, ItemImportFileContext $context): ItemImportContextApplyResult
    {
        $type = $context->type;

        if (ItemTypeEnum::MISC === $type) {
            $incomingRank = $context->rank;
            if (!is_int($incomingRank)) {
                return ItemImportContextApplyResult::invalid();
            }

            if (null !== $item->getRank() && $item->getRank() !== $incomingRank) {
                return ItemImportContextApplyResult::valid(sprintf(
                    'Conflit rank pour MISC id=%d (%d -> %d), conservation=%d',
                    $sourceId,
                    $item->getRank(),
                    $incomingRank,
                    $item->getRank(),
                ));
            }

            $item->setRank($incomingRank);

            return ItemImportContextApplyResult::valid();
        }

        $incomingListNumber = $context->listNumber;
        $incomingIsSpecial = $context->isSpecialList;
        if (!is_int($incomingListNumber)) {
            $item->setRank(null);

            return ItemImportContextApplyResult::valid();
        }

        $item->setRank(null);
        $item->addBookList($incomingListNumber, $incomingIsSpecial);

        return ItemImportContextApplyResult::valid();
    }
}
