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

final readonly class ItemImportContextApplyResult
{
    private function __construct(
        public bool $valid,
        public ?string $warning,
    ) {
    }

    public static function valid(?string $warning = null): self
    {
        return new self(true, $warning);
    }

    public static function invalid(): self
    {
        return new self(false, null);
    }
}
