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

namespace App\Tests\Unit\Identity\Infrastructure\Time;

use App\Identity\Infrastructure\Time\SystemIdentityClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SystemIdentityClockTest extends TestCase
{
    public function testNowReturnsDateTimeImmutable(): void
    {
        $clock = new SystemIdentityClock();

        self::assertInstanceOf(DateTimeImmutable::class, $clock->now());
    }
}
