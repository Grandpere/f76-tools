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

final readonly class ItemSourceFieldMergeDecision
{
    public function __construct(
        public string $field,
        public string $provider,
        public mixed $value,
        public string $reason,
        public mixed $otherValue = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'provider' => $this->provider,
            'value' => $this->value,
            'reason' => $this->reason,
            'other_value' => $this->otherValue,
        ];
    }
}
