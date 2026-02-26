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
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityIssuedTokenNotifier;
use App\Identity\UI\Security\IdentityLocaleRedirector;
use App\Service\TurnstileVerifier;
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
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
        private readonly IdentityIssuedTokenNotifier $identityIssuedTokenNotifier,
        private readonly IdentityLocaleRedirector $identityLocaleRedirector,
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $guardResult = $this->identityEmailFlowGuard->guard(
                $request,
                'forgot_password',
                'forgot_password',
                'security.forgot.flash.invalid_csrf',
                self::RATE_LIMIT_MAX_ATTEMPTS,
                self::RATE_LIMIT_WINDOW_SECONDS,
            );
            if (null !== $guardResult->failureFlashMessage) {
                $this->addFlash('warning', $guardResult->failureFlashMessage);

                return $this->identityLocaleRedirector->toRouteWithRequestLocale($request, 'app_forgot_password');
            }
            $payload = $guardResult->payload;

            $requestResult = $this->forgotPasswordRequestApplicationService->request($payload->email, new \DateTimeImmutable());
            if ($requestResult->isTokenIssued()) {
                $this->identityIssuedTokenNotifier->notifyResetPassword(
                    $requestResult->getEmail(),
                    $requestResult->getPlainToken(),
                    $request->getLocale(),
                    $request->getClientIp(),
                );
            }

            $this->addFlash('success', 'security.forgot.flash.request_sent');

            return $this->identityLocaleRedirector->toLogin($request);
        }

        return $this->render('security/forgot_password.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
