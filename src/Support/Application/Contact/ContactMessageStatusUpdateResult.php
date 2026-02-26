<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

enum ContactMessageStatusUpdateResult
{
    case INVALID_STATUS;
    case MESSAGE_NOT_FOUND;
    case UPDATED;
}
