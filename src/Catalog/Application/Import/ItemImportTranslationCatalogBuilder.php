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

use App\Catalog\Domain\Item\ItemTypeEnum;

final class ItemImportTranslationCatalogBuilder
{
    public function __construct(
        private readonly ItemImportValueNormalizer $valueNormalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function build(ItemTypeEnum $type, int $sourceId, array $row): ItemImportTranslationCatalog
    {
        $nameKey = sprintf('item.%s.%d.name', strtolower($type->value), $sourceId);
        $descKey = sprintf('item.%s.%d.desc', strtolower($type->value), $sourceId);
        $noteKey = sprintf('item.%s.%d.note', strtolower($type->value), $sourceId);

        $nameEn = $this->valueNormalizer->toNullableString($row['name_en'] ?? null);
        $nameDe = $this->valueNormalizer->toNullableString($row['name_de'] ?? null);
        $descEn = $this->valueNormalizer->toNullableString($row['desc_en'] ?? null);
        $descDe = $this->valueNormalizer->toNullableString($row['desc_de'] ?? null);
        $noteEn = $this->valueNormalizer->toNullableString($row['note_en'] ?? null);
        $noteDe = $this->valueNormalizer->toNullableString($row['note_de'] ?? null);

        $catalogEn = [];
        $catalogDe = [];

        if (null !== $nameEn) {
            $catalogEn[$nameKey] = $nameEn;
        } elseif (null !== $nameDe) {
            $catalogEn[$nameKey] = $nameDe;
        } else {
            $catalogEn[$nameKey] = sprintf('item_%d', $sourceId);
        }

        if (null !== $nameDe) {
            $catalogDe[$nameKey] = $nameDe;
        } elseif (null !== $nameEn) {
            $catalogDe[$nameKey] = $nameEn;
        }

        $resolvedDescKey = null;
        if (null !== $descEn || null !== $descDe) {
            $resolvedDescKey = $descKey;
            if (null !== $descEn) {
                $catalogEn[$descKey] = $descEn;
            } elseif (null !== $descDe) {
                $catalogEn[$descKey] = $descDe;
            }

            if (null !== $descDe) {
                $catalogDe[$descKey] = $descDe;
            } elseif (null !== $descEn) {
                $catalogDe[$descKey] = $descEn;
            }
        }

        $resolvedNoteKey = null;
        if (null !== $noteEn || null !== $noteDe) {
            $resolvedNoteKey = $noteKey;
            if (null !== $noteEn) {
                $catalogEn[$noteKey] = $noteEn;
            } elseif (null !== $noteDe) {
                $catalogEn[$noteKey] = $noteDe;
            }

            if (null !== $noteDe) {
                $catalogDe[$noteKey] = $noteDe;
            } elseif (null !== $noteEn) {
                $catalogDe[$noteKey] = $noteEn;
            }
        }

        return new ItemImportTranslationCatalog(
            $nameKey,
            $resolvedDescKey,
            $resolvedNoteKey,
            $catalogEn,
            $catalogDe,
        );
    }
}
