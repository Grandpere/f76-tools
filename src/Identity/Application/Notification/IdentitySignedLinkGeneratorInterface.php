<?php

declare(strict_types=1);

namespace App\Identity\Application\Notification;

interface IdentitySignedLinkGeneratorInterface
{
    public function generateVerificationUrl(string $locale, string $token): string;

    public function generateResetPasswordUrl(string $locale, string $token): string;
}
