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

interface MinervaRotationRefresher
{
    /**
     * @return array{
     *     expectedWindows: int,
     *     missingWindows: int,
     *     covered: bool,
     *     performed: bool,
     *     deleted: int,
     *     inserted: int,
     *     skipped: int
     * }
     */
    public function refresh(DateTimeImmutable $from, DateTimeImmutable $to, bool $dryRun = false): array;
}
