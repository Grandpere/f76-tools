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

enum IdentityEmailFlow: string
{
    case REGISTER = 'register';
    case FORGOT_PASSWORD = 'forgot_password';
    case RESEND_VERIFICATION = 'resend_verification';
    case CONTACT = 'contact';

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
            self::CONTACT => 'security.contact.flash.invalid_csrf',
        };
    }

    public function failureRoute(): string
    {
        return match ($this) {
            self::REGISTER => 'app_register',
            self::FORGOT_PASSWORD => 'app_forgot_password',
            self::RESEND_VERIFICATION => 'app_resend_verification',
            self::CONTACT => 'app_contact',
        };
    }

    public function maxAttempts(): int
    {
        return match ($this) {
            self::REGISTER => 3,
            self::FORGOT_PASSWORD => 3,
            self::RESEND_VERIFICATION => 3,
            self::CONTACT => 5,
        };
    }

    public function windowSeconds(): int
    {
        return match ($this) {
            self::REGISTER => 600,
            self::FORGOT_PASSWORD => 900,
            self::RESEND_VERIFICATION => 1800,
            self::CONTACT => 300,
        };
    }
}
