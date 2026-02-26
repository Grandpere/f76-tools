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

use App\Identity\Application\ForgotPassword\ForgotPasswordRequestApplicationService;
use App\Security\SignedUrlGenerator;
use App\Service\AuthRequestThrottler;
use App\Service\TurnstileVerifier;
use App\Security\AuthEventLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ForgotPasswordController extends AbstractController
{
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly ForgotPasswordRequestApplicationService $forgotPasswordRequestApplicationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly SignedUrlGenerator $signedUrlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly MailerInterface $mailer,
        private readonly AuthRequestThrottler $requestThrottler,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('forgot_password', $csrfToken))) {
                $this->authEventLogger->warning('security.auth.forgot_password.invalid_csrf', null, $request->getClientIp());
                $this->addFlash('warning', 'security.forgot.flash.invalid_csrf');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }
            if ('' !== trim((string) $request->request->get('website', ''))) {
                $this->authEventLogger->warning('security.auth.forgot_password.honeypot_triggered', null, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            if (!$this->turnstileVerifier->verify((string) $request->request->get('cf-turnstile-response', ''), $request->getClientIp())) {
                $this->authEventLogger->warning('security.auth.forgot_password.captcha_invalid', $email, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.captcha_invalid');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }

            if ($this->requestThrottler->hitAndIsLimited(
                scope: 'forgot_password',
                clientIp: $request->getClientIp(),
                email: $email,
                maxAttempts: self::RATE_LIMIT_MAX_ATTEMPTS,
                windowSeconds: self::RATE_LIMIT_WINDOW_SECONDS,
            )) {
                $this->authEventLogger->warning('security.auth.forgot_password.rate_limited', $email, $request->getClientIp(), [
                    'scope' => 'forgot_password',
                    'maxAttempts' => self::RATE_LIMIT_MAX_ATTEMPTS,
                    'windowSeconds' => self::RATE_LIMIT_WINDOW_SECONDS,
                ]);
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }

            $requestResult = $this->forgotPasswordRequestApplicationService->request($email, new \DateTimeImmutable());
            if ($requestResult->isTokenIssued()) {
                $plainToken = $requestResult->getPlainToken();
                $targetEmail = $requestResult->getEmail();
                if (is_string($plainToken) && is_string($targetEmail)) {
                    $resetUrl = $this->signedUrlGenerator->generate('app_reset_password', [
                        'locale' => $request->getLocale(),
                        'token' => $plainToken,
                    ]);
                    try {
                        $this->mailer->send(
                            (new Email())
                                ->from('no-reply@f76.local')
                                ->to($targetEmail)
                                ->subject($this->translator->trans('security.forgot.email_subject'))
                                ->text(sprintf("%s\n\n%s", $this->translator->trans('security.forgot.email_intro'), $resetUrl)),
                        );
                    } catch (\Throwable) {
                        // Keep same user-facing response to avoid account enumeration.
                    }

                    $this->authEventLogger->info('security.auth.forgot_password.reset_token_issued', $targetEmail, $request->getClientIp());
                }
            }

            $this->addFlash('success', 'security.forgot.flash.request_sent');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        return $this->render('security/forgot_password.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
