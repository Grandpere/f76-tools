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

namespace App\Tests\Unit\Catalog\NukeCode;

use App\Catalog\Application\NukeCode\NukeCodeResetCalculator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class NukeCodeResetCalculatorTest extends TestCase
{
    public function testComputesNextWednesdayAtMidnightUtc(): void
    {
        $calculator = new NukeCodeResetCalculator();

        $next = $calculator->nextResetUtc(new DateTimeImmutable('2026-03-05T12:00:00+00:00'));

        self::assertSame('2026-03-11T00:00:00+00:00', $next->format(DATE_ATOM));
    }

    public function testAlwaysReturnsFutureWednesdayWhenAlreadyWednesday(): void
    {
        $calculator = new NukeCodeResetCalculator();

        $next = $calculator->nextResetUtc(new DateTimeImmutable('2026-03-04T00:00:00+00:00'));

        self::assertSame('2026-03-11T00:00:00+00:00', $next->format(DATE_ATOM));
        self::assertSame('UTC', $next->getTimezone()->getName());
    }

    public function testConvertsFromLocalTimezoneBeforeCalculation(): void
    {
        $calculator = new NukeCodeResetCalculator();

        $parisNow = new DateTimeImmutable('2026-03-05 10:00:00', new DateTimeZone('Europe/Paris'));
        $next = $calculator->nextResetUtc($parisNow);

        self::assertSame('2026-03-11T00:00:00+00:00', $next->format(DATE_ATOM));
    }
}
