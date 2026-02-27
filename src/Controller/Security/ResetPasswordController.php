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

use App\Identity\Application\ResetPassword\ResetPasswordApplicationService;
use App\Identity\Application\Time\IdentityClockInterface;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentitySignedTokenFailureResolver;
use App\Identity\UI\Security\ResetPasswordFeedbackMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    use IdentitySignedTokenValidationControllerTrait;

    public function __construct(
        private readonly ResetPasswordApplicationService $resetPasswordApplicationService,
        private readonly IdentityClockInterface $identityClock,
        private readonly ResetPasswordFeedbackMapper $resetPasswordFeedbackMapper,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly IdentitySignedTokenFailureResolver $identitySignedTokenFailureResolver,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function __invoke(string $token, Request $request): Response
    {
        $validationFailureFlashMessage = $this->resolveSignedTokenFailureFlashMessage(
            $request,
            fn (): bool => $this->resetPasswordApplicationService->canResetToken($token, $this->identityClock->now()),
            'security.reset.flash.invalid_or_expired',
        );
        if (null !== $validationFailureFlashMessage) {
            return $this->identityFlashResponder->warningToLogin($request, $validationFailureFlashMessage);
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('reset_password', $csrfToken))) {
                return $this->identityFlashResponder->flashToCurrentUri($request, 'warning', 'security.reset.flash.invalid_csrf');
            }

            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            $result = $this->resetPasswordApplicationService->resetByPlainToken(
                $token,
                $password,
                $passwordConfirm,
                $this->identityClock->now(),
            );
            $feedback = $this->resetPasswordFeedbackMapper->map($result);

            if ($feedback['redirectToLogin']) {
                return $this->identityFlashResponder->flashToLogin($request, $feedback['flashType'], $feedback['flashMessage']);
            }

            return $this->identityFlashResponder->flashToCurrentUri($request, $feedback['flashType'], $feedback['flashMessage']);
        }

        return $this->render('security/reset_password.html.twig');
    }

    protected function identitySignedTokenFailureResolver(): IdentitySignedTokenFailureResolver
    {
        return $this->identitySignedTokenFailureResolver;
    }
}
