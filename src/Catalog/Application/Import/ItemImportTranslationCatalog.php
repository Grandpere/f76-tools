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

final readonly class ItemImportTranslationCatalog
{
    /**
     * @param array<string, string> $catalogEn
     * @param array<string, string> $catalogDe
     */
    public function __construct(
        public string $nameKey,
        public ?string $descKey,
        public ?string $noteKey,
        public array $catalogEn,
        public array $catalogDe,
    ) {
    }
}
