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
use App\Identity\Application\Time\IdentityClockInterface;
use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentityIssuedTokenNotifier;
use App\Service\TurnstileVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private readonly ForgotPasswordRequestApplicationService $forgotPasswordRequestApplicationService,
        private readonly IdentityClockInterface $identityClock,
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly IdentityIssuedTokenNotifier $identityIssuedTokenNotifier,
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $flow = IdentityEmailFlow::FORGOT_PASSWORD;
            $guardResult = $this->identityEmailFlowGuard->guard($request, $flow);
            if (null !== $guardResult->failureFlashMessage) {
                return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), $guardResult->failureFlashMessage);
            }
            $payload = $guardResult->payload;

            $requestResult = $this->forgotPasswordRequestApplicationService->request($payload->email, $this->identityClock->now());
            if ($requestResult->isTokenIssued()) {
                $this->identityIssuedTokenNotifier->notifyResetPassword(
                    $requestResult->getEmail(),
                    $requestResult->getPlainToken(),
                    $request->getLocale(),
                    $request->getClientIp(),
                );
            }

            return $this->identityFlashResponder->successToLogin($request, 'security.forgot.flash.request_sent');
        }

        return $this->render('security/forgot_password.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
