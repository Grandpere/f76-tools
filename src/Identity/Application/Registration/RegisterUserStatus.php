<?php

declare(strict_types=1);

namespace App\Identity\Application\Registration;

enum RegisterUserStatus: string
{
    case INVALID_EMAIL = 'invalid_email';
    case PASSWORD_TOO_SHORT = 'password_too_short';
    case PASSWORD_MISMATCH = 'password_mismatch';
    case EMAIL_EXISTS = 'email_exists';
    case SUCCESS = 'success';
}
