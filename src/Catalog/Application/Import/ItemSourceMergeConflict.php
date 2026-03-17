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

final readonly class ItemSourceMergeConflict
{
    public function __construct(
        public string $field,
        public mixed $valueA,
        public mixed $valueB,
        public string $reason,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'value_a' => $this->valueA,
            'value_b' => $this->valueB,
            'reason' => $this->reason,
        ];
    }
}
