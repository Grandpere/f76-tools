<?php

declare(strict_types=1);

namespace App\Support\Application\AdminUser;

enum ToggleUserActiveResult
{
    case ACTOR_REQUIRED;
    case USER_NOT_FOUND;
    case CANNOT_CHANGE_SELF;
    case UPDATED;
}
