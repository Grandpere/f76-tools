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

enum GenerateResetLinkStatus
{
    case USER_NOT_FOUND;
    case GLOBAL_RATE_LIMITED;
    case COOLDOWN_RATE_LIMITED;
    case GENERATED;
}
