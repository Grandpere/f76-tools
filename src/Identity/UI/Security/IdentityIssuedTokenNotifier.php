<?php

declare(strict_types=1);

namespace App\Identity\UI\Security;

use App\Identity\Application\Notification\IdentityLinkEmailSenderInterface;
use App\Security\AuthEventLogger;

final class IdentityIssuedTokenNotifier
{
    public function __construct(
        private readonly IdentityLinkEmailSenderInterface $identityLinkEmailSender,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    public function notifyVerification(
        ?string $email,
        ?string $plainToken,
        string $locale,
        ?string $clientIp,
        string $event,
    ): void {
        if (!is_string($email) || !is_string($plainToken)) {
            return;
        }

        $this->identityLinkEmailSender->sendVerificationLink($email, $locale, $plainToken);
        $this->authEventLogger->info($event, $email, $clientIp);
    }

    public function notifyResetPassword(
        ?string $email,
        ?string $plainToken,
        string $locale,
        ?string $clientIp,
    ): void {
        if (!is_string($email) || !is_string($plainToken)) {
            return;
        }

        $this->identityLinkEmailSender->sendResetPasswordLink($email, $locale, $plainToken);
        $this->authEventLogger->info('security.auth.forgot_password.reset_token_issued', $email, $clientIp);
    }
}
