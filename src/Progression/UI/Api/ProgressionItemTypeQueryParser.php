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

use App\Domain\Item\ItemTypeEnum;

final class ProgressionItemTypeQueryParser
{
    public function parse(mixed $value): ItemTypeEnum|false|null
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_string($value)) {
            return false;
        }

        return ItemTypeEnum::tryFrom(strtoupper(trim($value))) ?? false;
    }
}

