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

namespace App\Controller\Security;

use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Security\AuthEventLogger;
use App\Service\TurnstileVerifier;
use App\Support\Application\Contact\ContactMessageApplicationService;
use App\Support\Application\Contact\ContactMessageEmailSenderInterface;
use App\Support\Application\Contact\ContactSubmissionInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly AuthEventLogger $authEventLogger,
        private readonly ContactMessageApplicationService $contactMessageApplicationService,
        private readonly ContactMessageEmailSenderInterface $contactMessageEmailSender,
    ) {
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $flow = IdentityEmailFlow::CONTACT;
            $guardResult = $this->identityEmailFlowGuard->guard($request, $flow);
            if (null !== $guardResult->failureFlashMessage) {
                return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), $guardResult->failureFlashMessage);
            }
            $submissionInput = ContactSubmissionInput::create(
                $guardResult->payload->email,
                (string) $request->request->get('subject', ''),
                (string) $request->request->get('message', ''),
            );

            if (!$submissionInput->isValid()) {
                $this->authEventLogger->warning('security.auth.contact.invalid_payload', $submissionInput->email, $request->getClientIp());

                return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), 'security.contact.flash.invalid_input');
            }

            try {
                $this->contactMessageApplicationService->createMessage(
                    email: $submissionInput->email,
                    subject: $submissionInput->subject,
                    message: $submissionInput->message,
                    ip: $request->getClientIp(),
                );
            } catch (\Throwable) {
                $this->authEventLogger->warning('security.auth.contact.persistence_failed', $submissionInput->email, $request->getClientIp());

                return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), 'security.contact.flash.invalid_input');
            }

            try {
                $this->contactMessageEmailSender->send(
                    $submissionInput->email,
                    $submissionInput->subject,
                    $submissionInput->message,
                    $request->getClientIp(),
                );
            } catch (\Throwable) {
                $this->authEventLogger->warning('security.auth.contact.delivery_failed', $submissionInput->email, $request->getClientIp());
            }

            $this->authEventLogger->info('security.auth.contact.sent', $submissionInput->email, $request->getClientIp());

            return $this->identityFlashResponder->successToRoute($request, $flow->failureRoute(), 'security.contact.flash.sent');
        }

        return $this->render('security/contact.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
