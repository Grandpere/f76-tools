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
use App\Identity\Application\Notification\IdentityLinkEmailSenderInterface;
use App\Identity\Application\Guard\IdentityRequestGuardInterface;
use App\Identity\Application\Guard\IdentityRequestGuardResult;
use App\Service\TurnstileVerifier;
use App\Security\AuthEventLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotPasswordController extends AbstractController
{
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly ForgotPasswordRequestApplicationService $forgotPasswordRequestApplicationService,
        private readonly IdentityLinkEmailSenderInterface $identityLinkEmailSender,
        private readonly IdentityRequestGuardInterface $identityRequestGuard,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $guardResult = $this->identityRequestGuard->guard(
                'forgot_password',
                'forgot_password',
                (string) $request->request->get('_csrf_token', ''),
                (string) $request->request->get('website', ''),
                (string) $request->request->get('cf-turnstile-response', ''),
                $request->getClientIp(),
                $email,
                self::RATE_LIMIT_MAX_ATTEMPTS,
                self::RATE_LIMIT_WINDOW_SECONDS,
            );
            if (IdentityRequestGuardResult::INVALID_CSRF === $guardResult) {
                $this->authEventLogger->warning('security.auth.forgot_password.invalid_csrf', null, $request->getClientIp());
                $this->addFlash('warning', 'security.forgot.flash.invalid_csrf');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }
            if (IdentityRequestGuardResult::HONEYPOT === $guardResult) {
                $this->authEventLogger->warning('security.auth.forgot_password.honeypot_triggered', null, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }
            if (IdentityRequestGuardResult::CAPTCHA_INVALID === $guardResult) {
                $this->authEventLogger->warning('security.auth.forgot_password.captcha_invalid', $email, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.captcha_invalid');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }
            if (IdentityRequestGuardResult::RATE_LIMITED === $guardResult) {
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
                    $this->identityLinkEmailSender->sendResetPasswordLink($targetEmail, $request->getLocale(), $plainToken);

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
