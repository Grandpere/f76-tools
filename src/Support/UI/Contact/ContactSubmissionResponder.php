<?php

declare(strict_types=1);

/*
 * This file is part of a F76 project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Support\UI\Contact;

use App\Identity\Application\Security\AuthEventLogger;
use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Support\Application\Contact\ContactSubmissionStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContactSubmissionResponder
{
    public function __construct(
        private readonly AuthEventLogger $authEventLogger,
        private readonly IdentityFlashResponder $identityFlashResponder,
    ) {
    }

    public function guardFailed(Request $request, IdentityEmailFlow $flow, string $failureFlashMessage): Response
    {
        return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), $failureFlashMessage);
    }

    public function invalidPayload(Request $request, IdentityEmailFlow $flow, string $email): Response
    {
        $this->authEventLogger->warning('security.auth.contact.invalid_payload', $email, $request->getClientIp());

        return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), 'security.contact.flash.invalid_input');
    }

    public function persistenceFailed(Request $request, IdentityEmailFlow $flow, string $email): Response
    {
        $this->authEventLogger->warning('security.auth.contact.persistence_failed', $email, $request->getClientIp());

        return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), 'security.contact.flash.invalid_input');
    }

    public function submitted(Request $request, IdentityEmailFlow $flow, string $email, ContactSubmissionStatus $status): Response
    {
        if (ContactSubmissionStatus::PERSISTENCE_FAILED === $status) {
            return $this->persistenceFailed($request, $flow, $email);
        }

        if (ContactSubmissionStatus::SENT_WITH_DELIVERY_FAILURE === $status) {
            $this->authEventLogger->warning('security.auth.contact.delivery_failed', $email, $request->getClientIp());
        }

        $this->authEventLogger->info('security.auth.contact.sent', $email, $request->getClientIp());

        return $this->identityFlashResponder->successToRoute($request, $flow->failureRoute(), 'security.contact.flash.sent');
    }
}
