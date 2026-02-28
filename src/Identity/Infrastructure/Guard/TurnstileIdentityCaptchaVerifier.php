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

final class TurnstileIdentityCaptchaVerifier implements IdentityCaptchaVerifier
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
