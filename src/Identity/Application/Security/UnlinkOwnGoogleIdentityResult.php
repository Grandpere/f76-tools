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

namespace App\Identity\Application\Security;

enum UnlinkOwnGoogleIdentityResult: string
{
    case GOOGLE_IDENTITY_NOT_FOUND = 'google_identity_not_found';
    case LOCAL_PASSWORD_REQUIRED = 'local_password_required';
    case UNLINKED = 'unlinked';
}
