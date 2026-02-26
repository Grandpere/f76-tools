<?php

declare(strict_types=1);

namespace App\Identity\Application\Notification;

interface IdentityLinkEmailSenderInterface
{
    public function sendVerificationLink(string $email, string $locale, string $token): void;

    public function sendResetPasswordLink(string $email, string $locale, string $token): void;
}
