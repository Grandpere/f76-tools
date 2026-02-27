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

namespace App\Catalog\Domain\Item;

interface ItemInterface
{
    public function getSourceId(): int;

    public function getType(): ItemTypeEnum;

    public function getNameKey(): string;

    public function getDescKey(): ?string;

    public function getRank(): ?int;
}
