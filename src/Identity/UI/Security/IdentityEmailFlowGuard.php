<?php

declare(strict_types=1);

namespace App\Identity\UI\Security;

use App\Identity\Application\Guard\IdentityRequestGuardInterface;
use Symfony\Component\HttpFoundation\Request;

final class IdentityEmailFlowGuard
{
    public function __construct(
        private readonly IdentityEmailFormPayloadExtractor $identityEmailFormPayloadExtractor,
        private readonly IdentityRequestGuardInterface $identityRequestGuard,
        private readonly IdentityGuardFailureResponder $guardFailureResponder,
    ) {
    }

    public function guard(
        Request $request,
        string $scope,
        string $csrfTokenId,
        string $invalidCsrfFlashKey,
        int $maxAttempts,
        int $windowSeconds,
    ): IdentityEmailFlowGuardResult {
        $payload = $this->identityEmailFormPayloadExtractor->extract($request);
        $guardResult = $this->identityRequestGuard->guard(
            $scope,
            $csrfTokenId,
            $payload->csrfToken,
            $payload->honeypotValue,
            $payload->captchaToken,
            $request->getClientIp(),
            $payload->email,
            $maxAttempts,
            $windowSeconds,
        );

        $failureFlashMessage = $this->guardFailureResponder->resolveFlashMessage(
            $guardResult,
            $scope,
            $invalidCsrfFlashKey,
            $payload->email,
            $request,
            $maxAttempts,
            $windowSeconds,
        );

        return new IdentityEmailFlowGuardResult($payload, $failureFlashMessage);
    }
}
