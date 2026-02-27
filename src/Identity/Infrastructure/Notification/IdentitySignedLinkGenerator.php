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

namespace App\Identity\Infrastructure\Notification;

use App\Identity\Application\Notification\IdentitySignedLinkGeneratorInterface;
use App\Identity\Application\Security\SignedUrlGenerator;

final class IdentitySignedLinkGenerator implements IdentitySignedLinkGeneratorInterface
{
    public function __construct(
        private readonly SignedUrlGenerator $signedUrlGenerator,
    ) {
    }

    public function generateVerificationUrl(string $locale, string $token): string
    {
        return $this->signedUrlGenerator->generate('app_verify_email', [
            'locale' => $locale,
            'token' => $token,
        ]);
    }

    public function generateResetPasswordUrl(string $locale, string $token): string
    {
        return $this->signedUrlGenerator->generate('app_reset_password', [
            'locale' => $locale,
            'token' => $token,
        ]);
    }
}
