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
use App\Identity\Application\Time\IdentityClockInterface;
use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentityIssuedTokenNotifier;
use App\Identity\UI\Security\RegistrationFeedbackMapper;
use App\Security\AuthEventLogger;
use App\Service\TurnstileVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    use IdentityCaptchaRenderControllerTrait;

    public function __construct(
        private readonly RegisterUserApplicationService $registerUserApplicationService,
        private readonly IdentityClockInterface $identityClock,
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly IdentityIssuedTokenNotifier $identityIssuedTokenNotifier,
        private readonly RegistrationFeedbackMapper $registrationFeedbackMapper,
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
            $flow = IdentityEmailFlow::REGISTER;
            $guardResult = $this->identityEmailFlowGuard->guard($request, $flow);
            if (null !== $guardResult->failureFlashMessage) {
                return $this->identityFlashResponder->warningToRoute($request, $flow->failureRoute(), $guardResult->failureFlashMessage);
            }
            $payload = $guardResult->payload;

            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            $registerResult = $this->registerUserApplicationService->register(
                $payload->email,
                $password,
                $passwordConfirm,
                $this->identityClock->now(),
            );

            $warningMessage = $this->registrationFeedbackMapper->warningFlash($registerResult->getStatus());
            if (null !== $warningMessage) {
                return $this->identityFlashResponder->warningToRoute($request, 'app_register', $warningMessage);
            }

            $targetEmail = $registerResult->getEmail();
            $plainToken = $registerResult->getPlainVerificationToken();

            $this->identityIssuedTokenNotifier->notifyVerification(
                $targetEmail,
                $plainToken,
                $request->getLocale(),
                $request->getClientIp(),
                'security.auth.register.verification_token_issued',
            );

            $this->authEventLogger->info('security.auth.register.user_created', is_string($targetEmail) ? $targetEmail : $payload->email, $request->getClientIp(), [
                'emailVerificationRequired' => true,
            ]);

            return $this->identityFlashResponder->successToLogin($request, 'security.register.flash.success');
        }

        return $this->renderWithCaptchaSiteKey('security/register.html.twig');
    }

    protected function turnstileVerifier(): TurnstileVerifier
    {
        return $this->turnstileVerifier;
    }
}
