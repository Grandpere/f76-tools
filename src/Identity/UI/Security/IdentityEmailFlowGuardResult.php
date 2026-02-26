<?php

declare(strict_types=1);

namespace App\Identity\UI\Security;

final readonly class IdentityEmailFlowGuardResult
{
    public function __construct(
        public IdentityEmailFormPayload $payload,
        public ?string $failureFlashMessage,
    ) {
    }
}
