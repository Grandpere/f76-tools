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

namespace App\Identity\Infrastructure\Guard;

use App\Identity\Application\Guard\IdentityCaptchaVerifier;
use App\Identity\Application\Guard\IdentityRateLimiter;
use App\Identity\Application\Guard\IdentityRequestGuardResult;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class IdentityRequestGuard implements \App\Identity\Application\Guard\IdentityRequestGuard
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly IdentityCaptchaVerifier $captchaVerifier,
        private readonly IdentityRateLimiter $rateLimiter,
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
