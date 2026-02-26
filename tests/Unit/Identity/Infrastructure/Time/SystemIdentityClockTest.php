<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Time;

use App\Identity\Infrastructure\Time\SystemIdentityClock;
use PHPUnit\Framework\TestCase;

final class SystemIdentityClockTest extends TestCase
{
    public function testNowReturnsDateTimeImmutable(): void
    {
        $clock = new SystemIdentityClock();

        self::assertInstanceOf(\DateTimeImmutable::class, $clock->now());
    }
}
