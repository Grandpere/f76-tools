<?php

declare(strict_types=1);

namespace App\Identity\Application\Guard;

enum IdentityRequestGuardResult: string
{
    case ALLOWED = 'allowed';
    case INVALID_CSRF = 'invalid_csrf';
    case HONEYPOT = 'honeypot';
    case CAPTCHA_INVALID = 'captcha_invalid';
    case RATE_LIMITED = 'rate_limited';
}
