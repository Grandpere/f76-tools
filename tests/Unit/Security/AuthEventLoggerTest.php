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

namespace App\Tests\Unit\Security;

use App\Identity\Application\Security\AuthEventLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AuthEventLoggerTest extends TestCase
{
    public function testWarningHashesNormalizedEmailAndMergesContext(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'security.auth.test.warning',
                self::callback(static function (array $context): bool {
                    self::assertSame('203.0.113.1', $context['clientIp'] ?? null);
                    self::assertSame(hash('sha256', 'user@example.com'), $context['emailHash'] ?? null);
                    self::assertSame('register', $context['scope'] ?? null);

                    return true;
                }),
            );

        $authLogger = new AuthEventLogger($logger);
        $authLogger->warning('security.auth.test.warning', '  User@Example.com ', '203.0.113.1', [
            'scope' => 'register',
        ]);
    }

    public function testInfoLogsNullHashWhenEmailIsMissing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'security.auth.test.info',
                self::callback(static function (array $context): bool {
                    self::assertSame('198.51.100.7', $context['clientIp'] ?? null);
                    self::assertArrayHasKey('emailHash', $context);
                    self::assertNull($context['emailHash']);

                    return true;
                }),
            );

        $authLogger = new AuthEventLogger($logger);
        $authLogger->info('security.auth.test.info', null, '198.51.100.7');
    }
}
