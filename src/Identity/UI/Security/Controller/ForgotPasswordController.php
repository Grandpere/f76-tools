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

namespace App\Identity\UI\Security\Controller;

use App\Identity\Application\ForgotPassword\ForgotPasswordRequest;
use App\Identity\Application\ForgotPassword\ForgotPasswordRequestApplicationService;
use App\Identity\Application\Guard\IdentityCaptchaSiteKeyProvider;
use App\Identity\Application\Time\IdentityClock;
use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentityIssuedTokenNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotPasswordController extends AbstractController
{
    use IdentityEmailFlowControllerTrait;
    use IdentityCaptchaRenderControllerTrait;

    public function __construct(
        private readonly ForgotPasswordRequestApplicationService $forgotPasswordRequestApplicationService,
        private readonly IdentityClock $identityClock,
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly IdentityIssuedTokenNotifier $identityIssuedTokenNotifier,
        private readonly IdentityCaptchaSiteKeyProvider $captchaSiteKeyProvider,
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $flow = IdentityEmailFlow::FORGOT_PASSWORD;
            $payload = $this->resolveEmailFlowPayloadOrFailureResponse($request, $flow);
            if ($payload instanceof Response) {
                return $payload;
            }

            $requestResult = $this->forgotPasswordRequestApplicationService->request(ForgotPasswordRequest::fromRaw(
                $payload->email,
                $this->identityClock->now(),
            ));
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

        return $this->renderWithCaptchaSiteKey('security/forgot_password.html.twig');
    }

    protected function captchaSiteKeyProvider(): IdentityCaptchaSiteKeyProvider
    {
        return $this->captchaSiteKeyProvider;
    }

    protected function identityEmailFlowGuard(): IdentityEmailFlowGuard
    {
        return $this->identityEmailFlowGuard;
    }

    protected function identityFlashResponder(): IdentityFlashResponder
    {
        return $this->identityFlashResponder;
    }
}
