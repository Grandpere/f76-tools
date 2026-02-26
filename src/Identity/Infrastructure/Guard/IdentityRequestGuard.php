<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Guard;

use App\Identity\Application\Guard\IdentityRequestGuardInterface;
use App\Identity\Application\Guard\IdentityRequestGuardResult;
use App\Identity\Application\Guard\IdentityCaptchaVerifierInterface;
use App\Identity\Application\Guard\IdentityRateLimiterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class IdentityRequestGuard implements IdentityRequestGuardInterface
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly IdentityCaptchaVerifierInterface $captchaVerifier,
        private readonly IdentityRateLimiterInterface $rateLimiter,
    ) {
    }

    public function guard(
        string $scope,
        string $csrfTokenId,
        string $csrfToken,
        string $honeypotValue,
        string $captchaResponse,
        ?string $clientIp,
        string $email,
        int $maxAttempts,
        int $windowSeconds,
    ): IdentityRequestGuardResult {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($csrfTokenId, $csrfToken))) {
            return IdentityRequestGuardResult::INVALID_CSRF;
        }

        if ('' !== trim($honeypotValue)) {
            return IdentityRequestGuardResult::HONEYPOT;
        }

        if (!$this->captchaVerifier->verify($captchaResponse, $clientIp)) {
            return IdentityRequestGuardResult::CAPTCHA_INVALID;
        }

        if ($this->rateLimiter->hitAndIsLimited($scope, $clientIp, $email, $maxAttempts, $windowSeconds)) {
            return IdentityRequestGuardResult::RATE_LIMITED;
        }

        return IdentityRequestGuardResult::ALLOWED;
    }
}
