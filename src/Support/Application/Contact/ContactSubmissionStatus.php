<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

enum ContactSubmissionStatus
{
    case SENT;
    case SENT_WITH_DELIVERY_FAILURE;
    case PERSISTENCE_FAILED;
}
