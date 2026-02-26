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
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Service\TurnstileVerifier;
use App\Security\AuthEventLogger;
use App\Identity\UI\Security\IdentityIssuedTokenNotifier;
use App\Identity\UI\Security\RegistrationFeedbackMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly RegisterUserApplicationService $registerUserApplicationService,
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
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
            $guardResult = $this->identityEmailFlowGuard->guard(
                $request,
                'register',
                'register',
                'security.register.flash.invalid_csrf',
                self::RATE_LIMIT_MAX_ATTEMPTS,
                self::RATE_LIMIT_WINDOW_SECONDS,
            );
            if (null !== $guardResult->failureFlashMessage) {
                $this->addFlash('warning', $guardResult->failureFlashMessage);

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
            }
            $payload = $guardResult->payload;

            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            $registerResult = $this->registerUserApplicationService->register(
                $payload->email,
                $password,
                $passwordConfirm,
                new \DateTimeImmutable(),
            );

            $warningMessage = $this->registrationFeedbackMapper->warningFlash($registerResult->getStatus());
            if (null !== $warningMessage) {
                $this->addFlash('warning', $warningMessage);

                return $this->redirectToRoute('app_register', ['locale' => $request->getLocale()]);
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

            $this->addFlash('success', 'security.register.flash.success');
            $this->authEventLogger->info('security.auth.register.user_created', is_string($targetEmail) ? $targetEmail : $payload->email, $request->getClientIp(), [
                'emailVerificationRequired' => true,
            ]);

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        return $this->render('security/register.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
