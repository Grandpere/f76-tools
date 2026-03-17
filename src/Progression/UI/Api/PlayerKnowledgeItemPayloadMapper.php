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

use App\Catalog\Application\Import\ItemSourceMergePolicy;
use App\Catalog\Domain\Entity\ItemEntity;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PlayerKnowledgeItemPayloadMapper
{
    private const MERGE_PROVIDER_A = 'fandom';
    private const MERGE_PROVIDER_B = 'fallout_wiki';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ItemSourceMergePolicy $itemSourceMergePolicy,
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
     *     noteKey: string|null,
     *     note: string|null,
     *     isNew: bool,
     *     price: int|null,
     *     priceMinerva: int|null,
     *     dropRaid: bool,
     *     dropBurningSprings: bool,
     *     dropDailyOps: bool,
     *     dropBigfoot: bool,
     *     vendorRegs: bool,
     *     vendorSamuel: bool,
     *     vendorMortimer: bool,
     *     infoHtml: string|null,
     *     dropSourcesHtml: string|null,
     *     relationsHtml: string|null,
     *     sourceMerge: array{
     *         label:string,
     *         retained: array<string, array{
     *             provider:string,
     *             value:mixed,
     *             reason:string,
     *             otherValue:mixed
     *         }>,
     *         conflicts: list<array{
     *             field:string,
     *             valueA:mixed,
     *             valueB:mixed,
     *             reason:string
     *         }>
     *     }|null,
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
     *     noteKey: string|null,
     *     note: string|null,
     *     isNew: bool,
     *     price: int|null,
     *     priceMinerva: int|null,
     *     dropRaid: bool,
     *     dropBurningSprings: bool,
     *     dropDailyOps: bool,
     *     dropBigfoot: bool,
     *     vendorRegs: bool,
     *     vendorSamuel: bool,
     *     vendorMortimer: bool,
     *     infoHtml: string|null,
     *     dropSourcesHtml: string|null,
     *     relationsHtml: string|null,
     *     sourceMerge: array{
     *         label:string,
     *         retained: array<string, array{
     *             provider:string,
     *             value:mixed,
     *             reason:string,
     *             otherValue:mixed
     *         }>,
     *         conflicts: list<array{
     *             field:string,
     *             valueA:mixed,
     *             valueB:mixed,
     *             reason:string
     *         }>
     *     }|null,
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
        $note = null;
        if (null !== $item->getNoteKey()) {
            $note = $this->translator->trans($item->getNoteKey(), domain: 'items');
        }
        $sourceMerge = $this->buildSourceMergePayload($item);

        return [
            'id' => $item->getPublicId(),
            'sourceId' => $item->getSourceId(),
            'type' => $item->getType()->value,
            'nameKey' => $item->getNameKey(),
            'name' => $this->translator->trans($item->getNameKey(), domain: 'items'),
            'descKey' => $item->getDescKey(),
            'description' => $description,
            'noteKey' => $item->getNoteKey(),
            'note' => $note,
            'isNew' => $item->isNew(),
            'price' => $item->getPrice(),
            'priceMinerva' => $item->getPriceMinerva(),
            'dropRaid' => $item->isDropRaid(),
            'dropBurningSprings' => $item->isDropBurningSprings(),
            'dropDailyOps' => $item->isDropDailyOps(),
            'dropBigfoot' => $item->isDropBigfoot(),
            'vendorRegs' => $item->isVendorRegs(),
            'vendorSamuel' => $item->isVendorSamuel(),
            'vendorMortimer' => $item->isVendorMortimer(),
            'infoHtml' => $item->getInfoHtml(),
            'dropSourcesHtml' => $item->getDropSourcesHtml(),
            'relationsHtml' => $item->getRelationsHtml(),
            'sourceMerge' => $sourceMerge,
            'rank' => $item->getRank(),
            'listNumbers' => array_values(array_unique($listNumbers)),
            'isInSpecialList' => $isInSpecialList,
            'learned' => $learned,
        ];
    }

    /**
     * @return array{
     *     label:string,
     *     retained: array<string, array{
     *         provider:string,
     *         value:mixed,
     *         reason:string,
     *         otherValue:mixed
     *     }>,
     *     conflicts: list<array{
     *         field:string,
     *         valueA:mixed,
     *         valueB:mixed,
     *         reason:string
     *     }>
     * }|null
     */
    private function buildSourceMergePayload(ItemEntity $item): ?array
    {
        $result = $this->itemSourceMergePolicy->merge($item, self::MERGE_PROVIDER_A, self::MERGE_PROVIDER_B);
        if (null === $result) {
            return null;
        }

        $retained = [];
        foreach ($result->decisions as $decision) {
            $retained[$decision->field] = [
                'provider' => $decision->provider,
                'value' => $decision->value,
                'reason' => $decision->reason,
                'otherValue' => $decision->otherValue,
            ];
        }

        $conflicts = [];
        foreach ($result->conflicts as $conflict) {
            $conflicts[] = [
                'field' => $conflict->field,
                'valueA' => $conflict->valueA,
                'valueB' => $conflict->valueB,
                'reason' => $conflict->reason,
            ];
        }

        return [
            'label' => $result->label,
            'retained' => $retained,
            'conflicts' => $conflicts,
        ];
    }
}
