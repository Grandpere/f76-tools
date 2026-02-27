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

namespace App\Identity\Application\ResetPassword;

enum ResetPasswordResult: string
{
    case INVALID_OR_EXPIRED = 'invalid_or_expired';
    case PASSWORD_TOO_SHORT = 'password_too_short';
    case PASSWORD_MISMATCH = 'password_mismatch';
    case SUCCESS = 'success';
}
