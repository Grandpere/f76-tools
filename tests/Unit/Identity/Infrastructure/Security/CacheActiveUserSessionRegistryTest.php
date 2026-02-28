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

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\CacheActiveUserSessionRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheActiveUserSessionRegistryTest extends TestCase
{
    public function testRegisterAndListSessions(): void
    {
        $registry = new CacheActiveUserSessionRegistry(new ArrayAdapter());
        $now = new DateTimeImmutable('2026-02-28 10:00:00');

        $registry->registerOrTouch(12, 'sid-1', '127.0.0.1', 'Browser A', $now);
        $registry->registerOrTouch(12, 'sid-2', '127.0.0.2', 'Browser B', $now->modify('+2 minutes'));

        self::assertTrue($registry->hasAnySession(12));
        self::assertTrue($registry->hasSession(12, 'sid-1'));
        self::assertTrue($registry->hasSession(12, 'sid-2'));

        $sessions = $registry->listSessions(12);
        self::assertCount(2, $sessions);
        self::assertSame('sid-2', $sessions[0]->sessionId);
    }

    public function testRevokeOtherSessionsKeepsCurrentOne(): void
    {
        $registry = new CacheActiveUserSessionRegistry(new ArrayAdapter());
        $now = new DateTimeImmutable('2026-02-28 10:00:00');
        $registry->registerOrTouch(42, 'sid-a', '127.0.0.1', 'Browser A', $now);
        $registry->registerOrTouch(42, 'sid-b', '127.0.0.2', 'Browser B', $now->modify('+1 minute'));
        $registry->registerOrTouch(42, 'sid-c', '127.0.0.3', 'Browser C', $now->modify('+2 minute'));

        $revoked = $registry->revokeOtherSessions(42, 'sid-b');

        self::assertSame(2, $revoked);
        self::assertTrue($registry->hasSession(42, 'sid-b'));
        self::assertFalse($registry->hasSession(42, 'sid-a'));
        self::assertFalse($registry->hasSession(42, 'sid-c'));
    }
}
