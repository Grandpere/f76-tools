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

final class ItemImportItemHydrator
{
    public function __construct(
        private readonly ItemImportValueNormalizer $valueNormalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function hydrate(ItemEntity $item, array $row): void
    {
        $item->setPrice($this->valueNormalizer->toNullableInt($row['price'] ?? null));
        $item->setPriceMinerva($this->valueNormalizer->toNullableInt($row['price_minerva'] ?? null));
        $item->setIsNew($this->valueNormalizer->toBool($row['new'] ?? null));
        $item->setDropRaid($this->valueNormalizer->toBool($row['drop_raid'] ?? null));
        $item->setDropBurningSprings($this->valueNormalizer->toBoolFromRowAny($row, [
            'drop_burningsprings',
            'drop_burningssprings',
            'drop_burning_springs',
        ]));
        $item->setDropDailyOps($this->valueNormalizer->toBool($row['drop_dailyops'] ?? null));
        $item->setDropBigfoot($this->valueNormalizer->toBool($row['drop_bigfoot'] ?? null));
        $item->setVendorRegs($this->valueNormalizer->toBool($row['vendor_regs'] ?? null));
        $item->setVendorSamuel($this->valueNormalizer->toBool($row['vendor_samuel'] ?? null));
        $item->setVendorMortimer($this->valueNormalizer->toBool($row['vendor_mortimer'] ?? null));
        $item->setInfoHtml($this->valueNormalizer->toNullableString($row['info'] ?? null));
        $item->setDropSourcesHtml($this->valueNormalizer->toNullableString($row['drop_sources'] ?? null));
        $item->setRelationsHtml($this->valueNormalizer->toNullableString($row['relations'] ?? null));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{externalRef: string, externalUrl: string|null, metadata: array<string, mixed>}
     */
    public function buildExternalSourceData(array $row, int $sourceId): array
    {
        $externalRef = $this->valueNormalizer->toNullableString($row['form_id'] ?? null);
        if (null === $externalRef) {
            $externalRef = sprintf('source_id:%d', $sourceId);
        }

        $externalUrl = $this->valueNormalizer->toNullableString($row['wiki_url'] ?? null);

        /** @var array<string, mixed> $metadata */
        $metadata = $this->valueNormalizer->normalizePayload($row);
        $editorId = $metadata['editor_id'] ?? null;
        if (is_scalar($editorId) && '0' === (string) $editorId) {
            $metadata['editor_id'] = null;
        }

        return [
            'externalRef' => $externalRef,
            'externalUrl' => $externalUrl,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<mixed> $row
     *
     * @return array<string, mixed>
     */
    public function normalizeRow(array $row): array
    {
        return $this->valueNormalizer->normalizePayload($row);
    }
}
