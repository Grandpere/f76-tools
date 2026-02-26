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
use App\Identity\Application\ResetPassword\ResetPasswordResult;
use App\Security\SignedUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly ResetPasswordApplicationService $resetPasswordApplicationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly SignedUrlGenerator $signedUrlGenerator,
    ) {
    }

    #[Route('/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function __invoke(string $token, Request $request): Response
    {
        if (!$this->signedUrlGenerator->isRequestSignatureValid($request)) {
            $this->addFlash('warning', 'security.reset.flash.invalid_or_expired');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        if (!$this->resetPasswordApplicationService->canResetToken($token, new \DateTimeImmutable())) {
            $this->addFlash('warning', 'security.reset.flash.invalid_or_expired');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('reset_password', $csrfToken))) {
                $this->addFlash('warning', 'security.reset.flash.invalid_csrf');

                return new RedirectResponse($request->getUri());
            }

            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            $result = $this->resetPasswordApplicationService->resetByPlainToken(
                $token,
                $password,
                $passwordConfirm,
                new \DateTimeImmutable(),
            );

            if (ResetPasswordResult::PASSWORD_TOO_SHORT === $result) {
                $this->addFlash('warning', 'security.reset.flash.password_too_short');

                return new RedirectResponse($request->getUri());
            }
            if (ResetPasswordResult::PASSWORD_MISMATCH === $result) {
                $this->addFlash('warning', 'security.reset.flash.password_mismatch');

                return new RedirectResponse($request->getUri());
            }
            if (ResetPasswordResult::INVALID_OR_EXPIRED === $result) {
                $this->addFlash('warning', 'security.reset.flash.invalid_or_expired');

                return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
            }

            $this->addFlash('success', 'security.reset.flash.success');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        return $this->render('security/reset_password.html.twig');
    }
}
