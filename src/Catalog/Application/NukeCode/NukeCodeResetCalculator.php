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

namespace App\Catalog\Application\NukeCode;

use DateTimeImmutable;
use DateTimeZone;

final class NukeCodeResetCalculator
{
    private const TARGET_DAY = 3;

    public function nextResetUtc(DateTimeImmutable $now): DateTimeImmutable
    {
        $utcNow = $now->setTimezone(new DateTimeZone('UTC'));
        $currentDay = (int) $utcNow->format('w');

        $daysUntilTarget = (self::TARGET_DAY + 7 - $currentDay) % 7;
        if (0 === $daysUntilTarget) {
            $daysUntilTarget = 7;
        }

        return $utcNow
            ->modify(sprintf('+%d days', $daysUntilTarget))
            ->setTime(0, 0, 0);
    }
}
