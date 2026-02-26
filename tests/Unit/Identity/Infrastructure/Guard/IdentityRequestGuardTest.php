<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Guard;

use App\Identity\Application\Guard\IdentityCaptchaVerifierInterface;
use App\Identity\Application\Guard\IdentityRateLimiterInterface;
use App\Identity\Application\Guard\IdentityRequestGuardResult;
use App\Identity\Infrastructure\Guard\IdentityRequestGuard;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class IdentityRequestGuardTest extends TestCase
{
    private CsrfTokenManagerInterface&MockObject $csrf;
    private IdentityCaptchaVerifierInterface&MockObject $turnstile;
    private IdentityRateLimiterInterface&MockObject $throttler;

    protected function setUp(): void
    {
        $this->csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $this->turnstile = $this->createMock(IdentityCaptchaVerifierInterface::class);
        $this->throttler = $this->createMock(IdentityRateLimiterInterface::class);
    }

    public function testReturnsInvalidCsrfFirst(): void
    {
        $this->csrf->method('isTokenValid')->willReturn(false);

        $result = $this->service()->guard('register', 'register', 'bad', '', 'token', '127.0.0.1', 'a@b.c', 5, 300);

        self::assertSame(IdentityRequestGuardResult::INVALID_CSRF, $result);
    }

    public function testReturnsHoneypotWhenFilled(): void
    {
        $this->csrf->method('isTokenValid')->willReturn(true);

        $result = $this->service()->guard('register', 'register', 'ok', 'bot', 'token', '127.0.0.1', 'a@b.c', 5, 300);

        self::assertSame(IdentityRequestGuardResult::HONEYPOT, $result);
    }

    public function testReturnsCaptchaInvalid(): void
    {
        $this->csrf->method('isTokenValid')->willReturn(true);
        $this->turnstile->method('verify')->willReturn(false);

        $result = $this->service()->guard('register', 'register', 'ok', '', 'token', '127.0.0.1', 'a@b.c', 5, 300);

        self::assertSame(IdentityRequestGuardResult::CAPTCHA_INVALID, $result);
    }

    public function testReturnsRateLimited(): void
    {
        $this->csrf->method('isTokenValid')->willReturn(true);
        $this->turnstile->method('verify')->willReturn(true);
        $this->throttler->method('hitAndIsLimited')->willReturn(true);

        $result = $this->service()->guard('register', 'register', 'ok', '', 'token', '127.0.0.1', 'a@b.c', 5, 300);

        self::assertSame(IdentityRequestGuardResult::RATE_LIMITED, $result);
    }

    public function testReturnsAllowed(): void
    {
        $this->csrf->method('isTokenValid')->willReturn(true);
        $this->turnstile->method('verify')->willReturn(true);
        $this->throttler->method('hitAndIsLimited')->willReturn(false);

        $result = $this->service()->guard('register', 'register', 'ok', '', 'token', '127.0.0.1', 'a@b.c', 5, 300);

        self::assertSame(IdentityRequestGuardResult::ALLOWED, $result);
    }

    private function service(): IdentityRequestGuard
    {
        return new IdentityRequestGuard($this->csrf, $this->turnstile, $this->throttler);
    }
}
