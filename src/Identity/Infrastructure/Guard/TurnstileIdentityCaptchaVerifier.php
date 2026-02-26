<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Guard;

use App\Identity\Application\Guard\IdentityCaptchaVerifierInterface;
use App\Service\TurnstileVerifier;

final class TurnstileIdentityCaptchaVerifier implements IdentityCaptchaVerifierInterface
{
    public function __construct(
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {
    }

    public function verify(string $captchaResponse, ?string $clientIp): bool
    {
        return $this->turnstileVerifier->verify($captchaResponse, $clientIp);
    }
}
