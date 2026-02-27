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

use App\Entity\ItemEntity;

final class ItemImportItemHydrator
{
    public function __construct(
        private readonly ItemImportValueNormalizer $valueNormalizer,
    ) {
    }

    /**
     * @param array<mixed> $row
     */
    public function hydrate(ItemEntity $item, array $row): void
    {
        $item->setFormId($this->valueNormalizer->toNullableString($row['form_id'] ?? null));
        $item->setEditorId($this->valueNormalizer->toNullableString($row['editor_id'] ?? null));
        $item->setPrice($this->valueNormalizer->toNullableInt($row['price'] ?? null));
        $item->setPriceMinerva($this->valueNormalizer->toNullableInt($row['price_minerva'] ?? null));
        $item->setWikiUrl($this->valueNormalizer->toNullableString($row['wiki_url'] ?? null));
        $item->setTradeable(1 === $this->valueNormalizer->toNullableInt($row['tradeable'] ?? 0));
        $item->setIsNew($this->valueNormalizer->toBool($row['new'] ?? null));
        $item->setDropRaid($this->valueNormalizer->toBool($row['drop_raid'] ?? null));
        $item->setDropBurningSprings($this->valueNormalizer->toBoolFromRowAny($row, [
            'drop_burningsprings',
            'drop_burningssprings',
            'drop_burning_springs',
        ]));
        $item->setDropDailyOps($this->valueNormalizer->toBool($row['drop_dailyops'] ?? null));
        $item->setVendorRegs($this->valueNormalizer->toBool($row['vendor_regs'] ?? null));
        $item->setVendorSamuel($this->valueNormalizer->toBool($row['vendor_samuel'] ?? null));
        $item->setVendorMortimer($this->valueNormalizer->toBool($row['vendor_mortimer'] ?? null));
        $item->setInfoHtml($this->valueNormalizer->toNullableString($row['info'] ?? null));
        $item->setDropSourcesHtml($this->valueNormalizer->toNullableString($row['drop_sources'] ?? null));
        $item->setRelationsHtml($this->valueNormalizer->toNullableString($row['relations'] ?? null));
        $item->setPayload($this->valueNormalizer->normalizePayload($row));
    }
}
