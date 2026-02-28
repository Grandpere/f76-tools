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

namespace App\Identity\Application\Oidc;

enum GoogleOidcAuthenticationAction: string
{
    case IDENTITY_FOUND = 'identity_found';
    case AUTO_LINKED = 'auto_linked';
    case USER_CREATED = 'user_created';
}
