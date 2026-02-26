<?php

declare(strict_types=1);

namespace App\Support\Application\AdminUser;

enum GenerateResetLinkStatus
{
    case ACTOR_REQUIRED;
    case USER_NOT_FOUND;
    case GLOBAL_RATE_LIMITED;
    case COOLDOWN_RATE_LIMITED;
    case GENERATED;
}

