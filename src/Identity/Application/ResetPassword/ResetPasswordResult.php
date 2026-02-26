<?php

declare(strict_types=1);

namespace App\Identity\Application\ResetPassword;

enum ResetPasswordResult: string
{
    case INVALID_OR_EXPIRED = 'invalid_or_expired';
    case PASSWORD_TOO_SHORT = 'password_too_short';
    case PASSWORD_MISMATCH = 'password_mismatch';
    case SUCCESS = 'success';
}
