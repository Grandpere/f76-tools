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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly AuthEventLogger $authEventLogger,
        private readonly ContactMessageApplicationService $contactMessageApplicationService,
        private readonly string $contactRecipientEmail,
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
            $email = $guardResult->payload->email;
            $subject = trim((string) $request->request->get('subject', ''));
            $message = trim((string) $request->request->get('message', ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($subject) < 3 || mb_strlen($message) < 10) {
                $this->authEventLogger->warning('security.auth.contact.invalid_payload', $email, $request->getClientIp());

                return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), 'security.contact.flash.invalid_input');
            }

            try {
                $this->contactMessageApplicationService->createMessage(
                    email: $email,
                    subject: $subject,
                    message: $message,
                    ip: $request->getClientIp(),
                );
            } catch (\Throwable) {
                $this->authEventLogger->warning('security.auth.contact.persistence_failed', $email, $request->getClientIp());

                return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), 'security.contact.flash.invalid_input');
            }

            try {
                $mailSubject = sprintf('[F76 Contact] %s', $subject);
                $mailText = sprintf(
                    "From: %s\nIP: %s\n\n%s",
                    $email,
                    $request->getClientIp() ?? 'unknown',
                    $message,
                );
                $this->mailer->send(
                    (new Email())
                        ->from('no-reply@f76.local')
                        ->to($this->contactRecipientEmail)
                        ->replyTo($email)
                        ->subject($mailSubject)
                        ->text($mailText),
                );
            } catch (\Throwable) {
                $this->authEventLogger->warning('security.auth.contact.delivery_failed', $email, $request->getClientIp());
            }

            $this->authEventLogger->info('security.auth.contact.sent', $email, $request->getClientIp());

            return $this->identityFlashResponder->successToRoute($request, $flow->failureRoute(), 'security.contact.flash.sent');
        }

        return $this->render('security/contact.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
