<?php

declare(strict_types=1);

namespace App\Identity\Application\Guard;

interface IdentityCaptchaVerifierInterface
{
    public function verify(string $captchaResponse, ?string $clientIp): bool;
}
