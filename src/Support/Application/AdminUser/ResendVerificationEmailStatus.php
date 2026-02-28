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

namespace App\Support\Application\AdminUser;

enum ResendVerificationEmailStatus: string
{
    case USER_NOT_FOUND = 'user_not_found';
    case ALREADY_VERIFIED = 'already_verified';
    case RATE_LIMITED = 'rate_limited';
    case GENERATED = 'generated';
}
