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
        IdentityEmailFlow $flow,
    ): IdentityEmailFlowGuardResult {
        $payload = $this->identityEmailFormPayloadExtractor->extract($request);
        $guardResult = $this->identityRequestGuard->guard(
            $flow->value,
            $flow->csrfTokenId(),
            $payload->csrfToken,
            $payload->honeypotValue,
            $payload->captchaToken,
            $request->getClientIp(),
            $payload->email,
            $flow->maxAttempts(),
            $flow->windowSeconds(),
        );

        $failureFlashMessage = $this->guardFailureResponder->resolveFlashMessage(
            $guardResult,
            $flow->value,
            $flow->invalidCsrfFlashKey(),
            $payload->email,
            $request,
            $flow->maxAttempts(),
            $flow->windowSeconds(),
        );

        return new IdentityEmailFlowGuardResult($payload, $failureFlashMessage);
    }
}
