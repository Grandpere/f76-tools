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

namespace App\Progression\UI\Api;

use App\Catalog\Domain\Entity\ItemEntity;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PlayerKnowledgeItemPayloadMapper
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<array{item: ItemEntity, learned: bool}> $catalogRows
     *
     * @return list<array{
     *     id: string,
     *     sourceId: int,
     *     type: string,
     *     nameKey: string,
     *     name: string,
     *     descKey: string|null,
     *     description: string|null,
     *     isNew: bool,
     *     price: int|null,
     *     priceMinerva: int|null,
     *     dropRaid: bool,
     *     dropBurningSprings: bool,
     *     dropDailyOps: bool,
     *     vendorRegs: bool,
     *     vendorSamuel: bool,
     *     vendorMortimer: bool,
     *     infoHtml: string|null,
     *     dropSourcesHtml: string|null,
     *     relationsHtml: string|null,
     *     rank: int|null,
     *     listNumbers: list<int>,
     *     isInSpecialList: bool,
     *     learned: bool
     * }>
     */
    public function mapCatalogRows(array $catalogRows): array
    {
        $payload = [];
        foreach ($catalogRows as $row) {
            $payload[] = $this->map($row['item'], $row['learned']);
        }

        return $payload;
    }

    /**
     * @return array{
     *     id: string,
     *     sourceId: int,
     *     type: string,
     *     nameKey: string,
     *     name: string,
     *     descKey: string|null,
     *     description: string|null,
     *     isNew: bool,
     *     price: int|null,
     *     priceMinerva: int|null,
     *     dropRaid: bool,
     *     dropBurningSprings: bool,
     *     dropDailyOps: bool,
     *     vendorRegs: bool,
     *     vendorSamuel: bool,
     *     vendorMortimer: bool,
     *     infoHtml: string|null,
     *     dropSourcesHtml: string|null,
     *     relationsHtml: string|null,
     *     rank: int|null,
     *     listNumbers: list<int>,
     *     isInSpecialList: bool,
     *     learned: bool
     * }
     */
    public function map(ItemEntity $item, bool $learned): array
    {
        $listNumbers = [];
        $isInSpecialList = false;

        foreach ($item->getBookLists() as $bookList) {
            $listNumbers[] = $bookList->getListNumber();
            $isInSpecialList = $isInSpecialList || $bookList->isSpecialList();
        }

        sort($listNumbers);

        $description = null;
        if (null !== $item->getDescKey()) {
            $description = $this->translator->trans($item->getDescKey(), domain: 'items');
        }

        return [
            'id' => $item->getPublicId(),
            'sourceId' => $item->getSourceId(),
            'type' => $item->getType()->value,
            'nameKey' => $item->getNameKey(),
            'name' => $this->translator->trans($item->getNameKey(), domain: 'items'),
            'descKey' => $item->getDescKey(),
            'description' => $description,
            'isNew' => $item->isNew(),
            'price' => $item->getPrice(),
            'priceMinerva' => $item->getPriceMinerva(),
            'dropRaid' => $item->isDropRaid(),
            'dropBurningSprings' => $item->isDropBurningSprings(),
            'dropDailyOps' => $item->isDropDailyOps(),
            'vendorRegs' => $item->isVendorRegs(),
            'vendorSamuel' => $item->isVendorSamuel(),
            'vendorMortimer' => $item->isVendorMortimer(),
            'infoHtml' => $item->getInfoHtml(),
            'dropSourcesHtml' => $item->getDropSourcesHtml(),
            'relationsHtml' => $item->getRelationsHtml(),
            'rank' => $item->getRank(),
            'listNumbers' => array_values(array_unique($listNumbers)),
            'isInSpecialList' => $isInSpecialList,
            'learned' => $learned,
        ];
    }
}
