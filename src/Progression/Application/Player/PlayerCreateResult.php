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

namespace App\Progression\Application\Player;

use App\Entity\PlayerEntity;

final class PlayerCreateResult
{
    private function __construct(
        private readonly bool $ok,
        private readonly ?PlayerEntity $player,
    ) {
    }

    public static function success(PlayerEntity $player): self
    {
        return new self(true, $player);
    }

    public static function nameConflict(): self
    {
        return new self(false, null);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function getPlayer(): ?PlayerEntity
    {
        return $this->player;
    }
}

