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

use App\Identity\Application\Guard\IdentityRequestGuard;
use App\Identity\Application\Guard\IdentityRequestGuardResult;
use App\Identity\Application\Security\AuthEventLogger;
use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityEmailFormPayloadExtractor;
use App\Identity\UI\Security\IdentityGuardFailureResponder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class IdentityEmailFlowGuardTest extends TestCase
{
    public function testGuardReturnsPayloadWhenAllowed(): void
    {
        $requestGuard = $this->createMock(IdentityRequestGuard::class);
        $requestGuard->expects(self::once())->method('guard')
            ->with(
                'register',
                'register',
                'csrf-token',
                '',
                'captcha-token',
                '127.0.0.1',
                'user@example.com',
                3,
                600,
            )
            ->willReturn(IdentityRequestGuardResult::ALLOWED);

        $guard = new IdentityEmailFlowGuard(
            new IdentityEmailFormPayloadExtractor(),
            $requestGuard,
            new IdentityGuardFailureResponder(new AuthEventLogger($this->createMock(LoggerInterface::class))),
        );

        $result = $guard->guard(
            Request::create('/register', 'POST', [
                'email' => '  USER@Example.com ',
                '_csrf_token' => 'csrf-token',
                'website' => '',
                'cf-turnstile-response' => 'captcha-token',
            ], [], [], ['REMOTE_ADDR' => '127.0.0.1']),
            IdentityEmailFlow::REGISTER,
        );

        self::assertNull($result->failureFlashMessage);
        self::assertSame('user@example.com', $result->payload->email);
    }

    public function testGuardReturnsFailureMessageWhenRateLimited(): void
    {
        $requestGuard = $this->createMock(IdentityRequestGuard::class);
        $requestGuard->method('guard')->willReturn(IdentityRequestGuardResult::RATE_LIMITED);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $guard = new IdentityEmailFlowGuard(
            new IdentityEmailFormPayloadExtractor(),
            $requestGuard,
            new IdentityGuardFailureResponder(new AuthEventLogger($logger)),
        );

        $result = $guard->guard(
            Request::create('/forgot-password', 'POST', [
                'email' => 'user@example.com',
                '_csrf_token' => 'csrf-token',
                'website' => '',
                'cf-turnstile-response' => 'captcha-token',
            ]),
            IdentityEmailFlow::FORGOT_PASSWORD,
        );

        self::assertSame('security.auth.flash.rate_limited', $result->failureFlashMessage);
    }
}
