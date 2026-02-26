<?php

declare(strict_types=1);

namespace App\Identity\UI\Security;

use App\Security\SignedUrlGenerator;
use Symfony\Component\HttpFoundation\Request;

final class IdentitySignedTokenFailureResolver
{
    public function __construct(
        private readonly SignedUrlGenerator $signedUrlGenerator,
    ) {
    }

    /**
     * @param callable(): bool $tokenValidator
     */
    public function resolve(Request $request, callable $tokenValidator, string $invalidFlashMessage): ?string
    {
        if (!$this->signedUrlGenerator->isRequestSignatureValid($request)) {
            return $invalidFlashMessage;
        }

        if (!$tokenValidator()) {
            return $invalidFlashMessage;
        }

        return null;
    }
}
