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
use App\Identity\Application\Guard\IdentityRequestGuardInterface;
use App\Identity\Application\Guard\IdentityRequestGuardResult;
use App\Service\TurnstileVerifier;
use App\Security\AuthEventLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResendVerificationController extends AbstractController
{
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly ResendVerificationRequestApplicationService $resendVerificationRequestApplicationService,
        private readonly IdentityLinkEmailSenderInterface $identityLinkEmailSender,
        private readonly IdentityRequestGuardInterface $identityRequestGuard,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $guardResult = $this->identityRequestGuard->guard(
                'resend_verification',
                'resend_verification',
                (string) $request->request->get('_csrf_token', ''),
                (string) $request->request->get('website', ''),
                (string) $request->request->get('cf-turnstile-response', ''),
                $request->getClientIp(),
                $email,
                self::RATE_LIMIT_MAX_ATTEMPTS,
                self::RATE_LIMIT_WINDOW_SECONDS,
            );
            if (IdentityRequestGuardResult::INVALID_CSRF === $guardResult) {
                $this->authEventLogger->warning('security.auth.resend_verification.invalid_csrf', null, $request->getClientIp());
                $this->addFlash('warning', 'security.resend.flash.invalid_csrf');

                return $this->redirectToRoute('app_resend_verification', ['locale' => $request->getLocale()]);
            }
            if (IdentityRequestGuardResult::HONEYPOT === $guardResult) {
                $this->authEventLogger->warning('security.auth.resend_verification.honeypot_triggered', null, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_resend_verification', ['locale' => $request->getLocale()]);
            }
            if (IdentityRequestGuardResult::CAPTCHA_INVALID === $guardResult) {
                $this->authEventLogger->warning('security.auth.resend_verification.captcha_invalid', $email, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.captcha_invalid');

                return $this->redirectToRoute('app_resend_verification', ['locale' => $request->getLocale()]);
            }
            if (IdentityRequestGuardResult::RATE_LIMITED === $guardResult) {
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
