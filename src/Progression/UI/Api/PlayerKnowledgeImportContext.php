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

use App\Progression\Domain\Entity\PlayerEntity;

final readonly class PlayerKnowledgeImportContext
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public PlayerEntity $player,
        public array $payload,
    ) {
    }
}
