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

namespace App\Tests\Unit\Identity\UI\Security;

use App\Identity\Application\Notification\IdentityLinkEmailSenderInterface;
use App\Identity\UI\Security\IdentityIssuedTokenNotifier;
use App\Security\AuthEventLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class IdentityIssuedTokenNotifierTest extends TestCase
{
    public function testNotifyVerificationSkipsWhenTokenMissing(): void
    {
        /** @var IdentityLinkEmailSenderInterface&MockObject $sender */
        $sender = $this->createMock(IdentityLinkEmailSenderInterface::class);
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $authLogger = new AuthEventLogger($logger);

        $sender->expects(self::never())->method('sendVerificationLink');
        $logger->expects(self::never())->method('info');

        $notifier = new IdentityIssuedTokenNotifier($sender, $authLogger);
        $notifier->notifyVerification('user@example.com', null, 'fr', '127.0.0.1', 'security.auth.register.user_created');
    }

    public function testNotifyResetPasswordSendsAndLogs(): void
    {
        /** @var IdentityLinkEmailSenderInterface&MockObject $sender */
        $sender = $this->createMock(IdentityLinkEmailSenderInterface::class);
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $authLogger = new AuthEventLogger($logger);

        $sender
            ->expects(self::once())
            ->method('sendResetPasswordLink')
            ->with('user@example.com', 'fr', 'token123');
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'security.auth.forgot_password.reset_token_issued',
                self::arrayHasKey('clientIp'),
            );

        $notifier = new IdentityIssuedTokenNotifier($sender, $authLogger);
        $notifier->notifyResetPassword('user@example.com', 'token123', 'fr', '127.0.0.1');
    }
}
