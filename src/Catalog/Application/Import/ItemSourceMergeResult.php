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

final readonly class ItemSourceMergeResult
{
    /**
     * @param list<ItemSourceFieldMergeDecision> $decisions
     * @param list<ItemSourceMergeConflict>      $conflicts
     */
    public function __construct(
        public string $label,
        public array $decisions,
        public array $conflicts,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'decisions' => array_map(
                static fn (ItemSourceFieldMergeDecision $decision): array => $decision->toArray(),
                $this->decisions,
            ),
            'conflicts' => array_map(
                static fn (ItemSourceMergeConflict $conflict): array => $conflict->toArray(),
                $this->conflicts,
            ),
        ];
    }
}
