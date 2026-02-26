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

use App\Identity\Application\Notification\IdentityLinkEmailSenderInterface;
use App\Identity\Application\ResendVerification\ResendVerificationRequestApplicationService;
use App\Service\AuthRequestThrottler;
use App\Service\TurnstileVerifier;
use App\Security\AuthEventLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ResendVerificationController extends AbstractController
{
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly ResendVerificationRequestApplicationService $resendVerificationRequestApplicationService,
        private readonly IdentityLinkEmailSenderInterface $identityLinkEmailSender,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly AuthRequestThrottler $requestThrottler,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('resend_verification', $csrfToken))) {
                $this->authEventLogger->warning('security.auth.resend_verification.invalid_csrf', null, $request->getClientIp());
                $this->addFlash('warning', 'security.resend.flash.invalid_csrf');

                return $this->redirectToRoute('app_resend_verification', ['locale' => $request->getLocale()]);
            }
            if ('' !== trim((string) $request->request->get('website', ''))) {
                $this->authEventLogger->warning('security.auth.resend_verification.honeypot_triggered', null, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_resend_verification', ['locale' => $request->getLocale()]);
            }
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            if (!$this->turnstileVerifier->verify((string) $request->request->get('cf-turnstile-response', ''), $request->getClientIp())) {
                $this->authEventLogger->warning('security.auth.resend_verification.captcha_invalid', $email, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.captcha_invalid');

                return $this->redirectToRoute('app_resend_verification', ['locale' => $request->getLocale()]);
            }

            if ($this->requestThrottler->hitAndIsLimited(
                scope: 'resend_verification',
                clientIp: $request->getClientIp(),
                email: $email,
                maxAttempts: self::RATE_LIMIT_MAX_ATTEMPTS,
                windowSeconds: self::RATE_LIMIT_WINDOW_SECONDS,
            )) {
                $this->authEventLogger->warning('security.auth.resend_verification.rate_limited', $email, $request->getClientIp(), [
                    'scope' => 'resend_verification',
                    'maxAttempts' => self::RATE_LIMIT_MAX_ATTEMPTS,
                    'windowSeconds' => self::RATE_LIMIT_WINDOW_SECONDS,
                ]);
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_resend_verification', ['locale' => $request->getLocale()]);
            }

            $requestResult = $this->resendVerificationRequestApplicationService->request($email, new \DateTimeImmutable());
            if ($requestResult->isTokenIssued()) {
                $plainToken = $requestResult->getPlainToken();
                $targetEmail = $requestResult->getEmail();
                if (is_string($plainToken) && is_string($targetEmail)) {
                    $this->identityLinkEmailSender->sendVerificationLink($targetEmail, $request->getLocale(), $plainToken);

                    $this->authEventLogger->info('security.auth.resend_verification.token_issued', $targetEmail, $request->getClientIp());
                }
            }

            $this->addFlash('success', 'security.resend.flash.sent');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        return $this->render('security/resend_verification.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
