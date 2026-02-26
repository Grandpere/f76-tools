<?php

declare(strict_types=1);

namespace App\Identity\UI\Security;

use App\Identity\Application\Registration\RegisterUserStatus;

final class RegistrationFeedbackMapper
{
    public function warningFlash(RegisterUserStatus $status): ?string
    {
        return match ($status) {
            RegisterUserStatus::INVALID_EMAIL => 'security.register.flash.invalid_email',
            RegisterUserStatus::PASSWORD_TOO_SHORT => 'security.register.flash.password_too_short',
            RegisterUserStatus::PASSWORD_MISMATCH => 'security.register.flash.password_mismatch',
            RegisterUserStatus::EMAIL_EXISTS => 'security.register.flash.email_exists',
            RegisterUserStatus::SUCCESS => null,
        };
    }
}
