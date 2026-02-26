<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\UI\Security;

use App\Identity\Application\Guard\IdentityRequestGuardResult;
use App\Identity\UI\Security\IdentityGuardFailureResponder;
use App\Security\AuthEventLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class IdentityGuardFailureResponderTest extends TestCase
{
    public function testReturnsNullWhenAllowed(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $responder = new IdentityGuardFailureResponder(new AuthEventLogger($logger));
        $request = Request::create('/register', 'POST');

        self::assertNull($responder->resolveFlashMessage(
            IdentityRequestGuardResult::ALLOWED,
            'register',
            'security.register.flash.invalid_csrf',
            'user@example.com',
            $request,
            5,
            300,
        ));
    }

    public function testRateLimitedReturnsFlashMessageAndLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $responder = new IdentityGuardFailureResponder(new AuthEventLogger($logger));
        $request = Request::create('/register', 'POST');

        $flashMessage = $responder->resolveFlashMessage(
            IdentityRequestGuardResult::RATE_LIMITED,
            'register',
            'security.register.flash.invalid_csrf',
            'user@example.com',
            $request,
            5,
            300,
        );

        self::assertSame('security.auth.flash.rate_limited', $flashMessage);
    }
}
