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

use App\Identity\Application\Guard\IdentityCaptchaSiteKeyProvider;
use App\Identity\Application\ResendVerification\ResendVerificationRequest;
use App\Identity\Application\ResendVerification\ResendVerificationRequestApplicationService;
use App\Identity\Application\Time\IdentityClock;
use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentityIssuedTokenNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResendVerificationController extends AbstractController
{
    use IdentityEmailFlowControllerTrait;
    use IdentityCaptchaRenderControllerTrait;

    public function __construct(
        private readonly ResendVerificationRequestApplicationService $resendVerificationRequestApplicationService,
        private readonly IdentityClock $identityClock,
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly IdentityIssuedTokenNotifier $identityIssuedTokenNotifier,
        private readonly IdentityCaptchaSiteKeyProvider $captchaSiteKeyProvider,
    ) {
    }

    #[Route('/{_locale<en|fr|de>}/resend-verification', name: 'app_resend_verification', methods: ['GET', 'POST'], defaults: ['_locale' => 'en'])]
    #[Route('/resend-verification', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $flow = IdentityEmailFlow::RESEND_VERIFICATION;
            $payload = $this->resolveEmailFlowPayloadOrFailureResponse($request, $flow);
            if ($payload instanceof Response) {
                return $payload;
            }

            $requestResult = $this->resendVerificationRequestApplicationService->request(ResendVerificationRequest::fromRaw(
                $payload->email,
                $this->identityClock->now(),
            ));
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

        return $this->renderWithCaptchaSiteKey('security/resend_verification.html.twig');
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
