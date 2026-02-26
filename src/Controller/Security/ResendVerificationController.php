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

use App\Identity\Application\ResendVerification\ResendVerificationRequestApplicationService;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentityIssuedTokenNotifier;
use App\Service\TurnstileVerifier;
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
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly IdentityIssuedTokenNotifier $identityIssuedTokenNotifier,
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $guardResult = $this->identityEmailFlowGuard->guard(
                $request,
                'resend_verification',
                'resend_verification',
                'security.resend.flash.invalid_csrf',
                self::RATE_LIMIT_MAX_ATTEMPTS,
                self::RATE_LIMIT_WINDOW_SECONDS,
            );
            if (null !== $guardResult->failureFlashMessage) {
                return $this->identityFlashResponder->warningToRoute($request, 'app_resend_verification', $guardResult->failureFlashMessage);
            }
            $payload = $guardResult->payload;

            $requestResult = $this->resendVerificationRequestApplicationService->request($payload->email, new \DateTimeImmutable());
            if ($requestResult->isTokenIssued()) {
                $this->identityIssuedTokenNotifier->notifyVerification(
                    $requestResult->getEmail(),
                    $requestResult->getPlainToken(),
                    $request->getLocale(),
                    $request->getClientIp(),
                    'security.auth.resend_verification.token_issued',
                );
            }

            return $this->identityFlashResponder->successToLogin($request, 'security.resend.flash.sent');
        }

        return $this->render('security/resend_verification.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
