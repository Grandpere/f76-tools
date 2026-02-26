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

use App\Security\AuthEventLogger;
use App\Service\AuthRequestThrottler;
use App\Service\TurnstileVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ContactController extends AbstractController
{
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly MailerInterface $mailer,
        private readonly AuthRequestThrottler $requestThrottler,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly AuthEventLogger $authEventLogger,
        private readonly string $contactRecipientEmail,
    ) {
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('contact', $csrfToken))) {
                $this->authEventLogger->warning('security.auth.contact.invalid_csrf', null, $request->getClientIp());
                $this->addFlash('warning', 'security.contact.flash.invalid_csrf');

                return $this->redirectToRoute('app_contact', ['locale' => $request->getLocale()]);
            }

            if ('' !== trim((string) $request->request->get('website', ''))) {
                $this->authEventLogger->warning('security.auth.contact.honeypot_triggered', null, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_contact', ['locale' => $request->getLocale()]);
            }

            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $subject = trim((string) $request->request->get('subject', ''));
            $message = trim((string) $request->request->get('message', ''));

            if (!$this->turnstileVerifier->verify((string) $request->request->get('cf-turnstile-response', ''), $request->getClientIp())) {
                $this->authEventLogger->warning('security.auth.contact.captcha_invalid', $email, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.captcha_invalid');

                return $this->redirectToRoute('app_contact', ['locale' => $request->getLocale()]);
            }

            if ($this->requestThrottler->hitAndIsLimited(
                scope: 'contact',
                clientIp: $request->getClientIp(),
                email: $email,
                maxAttempts: self::RATE_LIMIT_MAX_ATTEMPTS,
                windowSeconds: self::RATE_LIMIT_WINDOW_SECONDS,
            )) {
                $this->authEventLogger->warning('security.auth.contact.rate_limited', $email, $request->getClientIp(), [
                    'scope' => 'contact',
                    'maxAttempts' => self::RATE_LIMIT_MAX_ATTEMPTS,
                    'windowSeconds' => self::RATE_LIMIT_WINDOW_SECONDS,
                ]);
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_contact', ['locale' => $request->getLocale()]);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($subject) < 3 || mb_strlen($message) < 10) {
                $this->authEventLogger->warning('security.auth.contact.invalid_payload', $email, $request->getClientIp());
                $this->addFlash('warning', 'security.contact.flash.invalid_input');

                return $this->redirectToRoute('app_contact', ['locale' => $request->getLocale()]);
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
            $this->addFlash('success', 'security.contact.flash.sent');

            return $this->redirectToRoute('app_contact', ['locale' => $request->getLocale()]);
        }

        return $this->render('security/contact.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
