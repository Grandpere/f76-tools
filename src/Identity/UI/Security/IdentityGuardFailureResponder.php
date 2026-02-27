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

namespace App\Identity\UI\Security;

use App\Identity\Application\Guard\IdentityRequestGuardResult;
use App\Security\AuthEventLogger;
use Symfony\Component\HttpFoundation\Request;

final class IdentityGuardFailureResponder
{
    public function __construct(
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    public function resolveFlashMessage(
        IdentityRequestGuardResult $guardResult,
        string $scope,
        string $invalidCsrfFlashKey,
        string $email,
        Request $request,
        int $maxAttempts,
        int $windowSeconds,
    ): ?string {
        if (IdentityRequestGuardResult::ALLOWED === $guardResult) {
            return null;
        }

        if (IdentityRequestGuardResult::INVALID_CSRF === $guardResult) {
            $this->authEventLogger->warning(sprintf('security.auth.%s.invalid_csrf', $scope), null, $request->getClientIp());

            return $invalidCsrfFlashKey;
        }

        if (IdentityRequestGuardResult::HONEYPOT === $guardResult) {
            $this->authEventLogger->warning(sprintf('security.auth.%s.honeypot_triggered', $scope), null, $request->getClientIp());

            return 'security.auth.flash.rate_limited';
        }

        if (IdentityRequestGuardResult::CAPTCHA_INVALID === $guardResult) {
            $this->authEventLogger->warning(sprintf('security.auth.%s.captcha_invalid', $scope), $email, $request->getClientIp());

            return 'security.auth.flash.captcha_invalid';
        }

        $this->authEventLogger->warning(sprintf('security.auth.%s.rate_limited', $scope), $email, $request->getClientIp(), [
            'scope' => $scope,
            'maxAttempts' => $maxAttempts,
            'windowSeconds' => $windowSeconds,
        ]);

        return 'security.auth.flash.rate_limited';
    }
}
