<?php

declare(strict_types=1);

namespace App\Identity\UI\Security;

enum IdentityEmailFlow: string
{
    case REGISTER = 'register';
    case FORGOT_PASSWORD = 'forgot_password';
    case RESEND_VERIFICATION = 'resend_verification';

    public function csrfTokenId(): string
    {
        return $this->value;
    }

    public function invalidCsrfFlashKey(): string
    {
        return match ($this) {
            self::REGISTER => 'security.register.flash.invalid_csrf',
            self::FORGOT_PASSWORD => 'security.forgot.flash.invalid_csrf',
            self::RESEND_VERIFICATION => 'security.resend.flash.invalid_csrf',
        };
    }

    public function failureRoute(): string
    {
        return match ($this) {
            self::REGISTER => 'app_register',
            self::FORGOT_PASSWORD => 'app_forgot_password',
            self::RESEND_VERIFICATION => 'app_resend_verification',
        };
    }

    public function maxAttempts(): int
    {
        return 5;
    }

    public function windowSeconds(): int
    {
        return 300;
    }
}
