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

namespace App\Tests\Unit\Service;

use App\Service\AuthRequestThrottler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class AuthRequestThrottlerTest extends TestCase
{
    public function testIsNotLimitedBelowThreshold(): void
    {
        $throttler = new AuthRequestThrottler(new ArrayAdapter());

        self::assertFalse($throttler->hitAndIsLimited('register', '127.0.0.1', 'a@example.com', 3, 60));
        self::assertFalse($throttler->hitAndIsLimited('register', '127.0.0.1', 'a@example.com', 3, 60));
        self::assertFalse($throttler->hitAndIsLimited('register', '127.0.0.1', 'a@example.com', 3, 60));
    }

    public function testIsLimitedWhenThresholdExceeded(): void
    {
        $throttler = new AuthRequestThrottler(new ArrayAdapter());

        self::assertFalse($throttler->hitAndIsLimited('forgot_password', '127.0.0.1', 'a@example.com', 2, 60));
        self::assertFalse($throttler->hitAndIsLimited('forgot_password', '127.0.0.1', 'a@example.com', 2, 60));
        self::assertTrue($throttler->hitAndIsLimited('forgot_password', '127.0.0.1', 'a@example.com', 2, 60));
    }

    public function testWindowExpiresAndCounterResets(): void
    {
        $cache = new ArrayAdapter();
        $throttler = new AuthRequestThrottler($cache);

        self::assertFalse($throttler->hitAndIsLimited('resend', '127.0.0.1', 'a@example.com', 1, 1));
        self::assertTrue($throttler->hitAndIsLimited('resend', '127.0.0.1', 'a@example.com', 1, 1));

        sleep(2);

        self::assertFalse($throttler->hitAndIsLimited('resend', '127.0.0.1', 'a@example.com', 1, 1));
    }

    public function testScopesAreIndependent(): void
    {
        $throttler = new AuthRequestThrottler(new ArrayAdapter());

        self::assertFalse($throttler->hitAndIsLimited('register', '127.0.0.1', 'a@example.com', 1, 60));
        self::assertFalse($throttler->hitAndIsLimited('forgot_password', '127.0.0.1', 'a@example.com', 1, 60));
    }
}
