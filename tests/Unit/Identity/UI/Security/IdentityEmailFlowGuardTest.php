<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\UI\Security;

use App\Identity\Application\Guard\IdentityRequestGuardInterface;
use App\Identity\Application\Guard\IdentityRequestGuardResult;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityEmailFormPayloadExtractor;
use App\Identity\UI\Security\IdentityGuardFailureResponder;
use App\Security\AuthEventLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class IdentityEmailFlowGuardTest extends TestCase
{
    public function testGuardReturnsPayloadWhenAllowed(): void
    {
        $requestGuard = $this->createMock(IdentityRequestGuardInterface::class);
        $requestGuard->expects(self::once())->method('guard')
            ->with(
                'register',
                'register',
                'csrf-token',
                '',
                'captcha-token',
                '127.0.0.1',
                'user@example.com',
                5,
                300,
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
            'register',
            'register',
            'security.register.flash.invalid_csrf',
            5,
            300,
        );

        self::assertNull($result->failureFlashMessage);
        self::assertSame('user@example.com', $result->payload->email);
    }

    public function testGuardReturnsFailureMessageWhenRateLimited(): void
    {
        $requestGuard = $this->createMock(IdentityRequestGuardInterface::class);
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
            'forgot_password',
            'forgot_password',
            'security.forgot.flash.invalid_csrf',
            5,
            300,
        );

        self::assertSame('security.auth.flash.rate_limited', $result->failureFlashMessage);
    }
}
