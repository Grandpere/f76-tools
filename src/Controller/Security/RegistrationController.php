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

use App\Identity\Application\Registration\RegisterUserApplicationService;
use App\Identity\Application\Registration\RegisterUserStatus;
use App\Security\SignedUrlGenerator;
use App\Service\AuthRequestThrottler;
use App\Service\TurnstileVerifier;
use App\Security\AuthEventLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly RegisterUserApplicationService $registerUserApplicationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly SignedUrlGenerator $signedUrlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly MailerInterface $mailer,
        private readonly AuthRequestThrottler $requestThrottler,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('register', $csrfToken))) {
                $this->authEventLogger->warning('security.auth.register.invalid_csrf', null, $request->getClientIp());
                $this->addFlash('warning', 'security.register.flash.invalid_csrf');

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
            }
            if ('' !== trim((string) $request->request->get('website', ''))) {
                $this->authEventLogger->warning('security.auth.register.honeypot_triggered', null, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
            }
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            if (!$this->turnstileVerifier->verify((string) $request->request->get('cf-turnstile-response', ''), $request->getClientIp())) {
                $this->authEventLogger->warning('security.auth.register.captcha_invalid', $email, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.captcha_invalid');

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
            }

            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            if ($this->requestThrottler->hitAndIsLimited(
                scope: 'register',
                clientIp: $request->getClientIp(),
                email: $email,
                maxAttempts: self::RATE_LIMIT_MAX_ATTEMPTS,
                windowSeconds: self::RATE_LIMIT_WINDOW_SECONDS,
            )) {
                $this->authEventLogger->warning('security.auth.register.rate_limited', $email, $request->getClientIp(), [
                    'scope' => 'register',
                    'maxAttempts' => self::RATE_LIMIT_MAX_ATTEMPTS,
                    'windowSeconds' => self::RATE_LIMIT_WINDOW_SECONDS,
                ]);
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
            }

            $registerResult = $this->registerUserApplicationService->register(
                $email,
                $password,
                $passwordConfirm,
                new \DateTimeImmutable(),
            );

            if (RegisterUserStatus::INVALID_EMAIL === $registerResult->getStatus()) {
                $this->addFlash('warning', 'security.register.flash.invalid_email');

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
            }
            if (RegisterUserStatus::PASSWORD_TOO_SHORT === $registerResult->getStatus()) {
                $this->addFlash('warning', 'security.register.flash.password_too_short');

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
            }
            if (RegisterUserStatus::PASSWORD_MISMATCH === $registerResult->getStatus()) {
                $this->addFlash('warning', 'security.register.flash.password_mismatch');

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
            }
            if (RegisterUserStatus::EMAIL_EXISTS === $registerResult->getStatus()) {
                $this->addFlash('warning', 'security.register.flash.email_exists');

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
            }

            $targetEmail = $registerResult->getEmail();
            $plainToken = $registerResult->getPlainVerificationToken();

            if (is_string($targetEmail) && is_string($plainToken)) {
                $verifyUrl = $this->signedUrlGenerator->generate('app_verify_email', [
                    'locale' => $request->getLocale(),
                    'token' => $plainToken,
                ]);

                try {
                    $this->mailer->send(
                        (new Email())
                            ->from('no-reply@f76.local')
                            ->to($targetEmail)
                            ->subject($this->translator->trans('security.verify.email_subject'))
                            ->text(sprintf("%s\n\n%s", $this->translator->trans('security.verify.email_intro'), $verifyUrl)),
                    );
                } catch (\Throwable) {
                    // Avoid disclosing transport details during registration flow.
                }
            }

            $this->addFlash('success', 'security.register.flash.success');
            $this->authEventLogger->info('security.auth.register.user_created', is_string($targetEmail) ? $targetEmail : $email, $request->getClientIp(), [
                'emailVerificationRequired' => true,
            ]);

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        return $this->render('security/register.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
