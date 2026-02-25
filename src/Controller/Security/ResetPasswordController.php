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

use App\Entity\UserEntity;
use App\Repository\UserEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserEntityRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function __invoke(string $token, Request $request): Response
    {
        $user = $this->resolveValidUserByToken($token);
        if (!$user instanceof UserEntity) {
            $this->addFlash('warning', 'security.reset.flash.invalid_or_expired');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('reset_password', $csrfToken))) {
                $this->addFlash('warning', 'security.reset.flash.invalid_csrf');

                return $this->redirectToRoute('app_reset_password', [
                    'locale' => $request->getLocale(),
                    'token' => $token,
                ]);
            }

            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            if (strlen($password) < 8) {
                $this->addFlash('warning', 'security.reset.flash.password_too_short');

                return $this->redirectToRoute('app_reset_password', [
                    'locale' => $request->getLocale(),
                    'token' => $token,
                ]);
            }
            if ($password !== $passwordConfirm) {
                $this->addFlash('warning', 'security.reset.flash.password_mismatch');

                return $this->redirectToRoute('app_reset_password', [
                    'locale' => $request->getLocale(),
                    'token' => $token,
                ]);
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setResetPasswordTokenHash(null);
            $user->setResetPasswordExpiresAt(null);
            $this->entityManager->flush();

            $this->addFlash('success', 'security.reset.flash.success');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        return $this->render('security/reset_password.html.twig');
    }

    private function resolveValidUserByToken(string $token): ?UserEntity
    {
        if ('' === trim($token)) {
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->userRepository->findOneByResetPasswordTokenHash($tokenHash);
        if (!$user instanceof UserEntity) {
            return null;
        }

        $expiresAt = $user->getResetPasswordExpiresAt();
        if (null === $expiresAt || $expiresAt < new \DateTimeImmutable()) {
            return null;
        }

        return $user;
    }
}
