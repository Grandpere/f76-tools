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

namespace App\Catalog\Application\Minerva;

use DateTimeImmutable;

interface MinervaRotationRegenerator
{
    /**
     * @return array{deleted: int, inserted: int, skipped: int}
     */
    public function regenerate(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
