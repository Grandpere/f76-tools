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

namespace App\Identity\Application\Guard;

enum IdentityRequestGuardResult: string
{
    case ALLOWED = 'allowed';
    case INVALID_CSRF = 'invalid_csrf';
    case HONEYPOT = 'honeypot';
    case CAPTCHA_INVALID = 'captcha_invalid';
    case RATE_LIMITED = 'rate_limited';
}
