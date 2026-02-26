<?php

declare(strict_types=1);

namespace App\Identity\UI\Security;

final readonly class IdentityEmailFormPayload
{
    public function __construct(
        public string $email,
        public string $csrfToken,
        public string $honeypotValue,
        public string $captchaToken,
    ) {
    }
}
