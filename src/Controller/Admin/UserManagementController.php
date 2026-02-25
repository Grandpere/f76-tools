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

namespace App\Controller\Admin;

use App\Entity\UserEntity;
use App\Repository\UserEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/users')]
final class UserManagementController extends AbstractController
{
    public function __construct(
        private readonly UserEntityRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/users.html.twig', [
            'users' => $this->userRepository->findAllOrdered(),
        ]);
    }

    #[Route('/{id<\d+>}/toggle-active', name: 'app_admin_users_toggle_active', methods: ['POST'])]
    public function toggleActive(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isValidToken($request, 'admin_users_toggle_active_'.$id)) {
            $this->addFlash('warning', 'admin_users.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $user = $this->userRepository->find($id);
        if (!$user instanceof UserEntity) {
            $this->addFlash('warning', 'admin_users.flash.user_not_found');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        if ($this->isCurrentUser($user)) {
            $this->addFlash('warning', 'admin_users.flash.cannot_change_self_active');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $user->setIsActive(!$user->isActive());
        $this->entityManager->flush();
        $this->addFlash('success', 'admin_users.flash.active_updated');

        return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
    }

    #[Route('/{id<\d+>}/toggle-admin', name: 'app_admin_users_toggle_admin', methods: ['POST'])]
    public function toggleAdmin(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isValidToken($request, 'admin_users_toggle_admin_'.$id)) {
            $this->addFlash('warning', 'admin_users.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $user = $this->userRepository->find($id);
        if (!$user instanceof UserEntity) {
            $this->addFlash('warning', 'admin_users.flash.user_not_found');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        if ($this->isCurrentUser($user)) {
            $this->addFlash('warning', 'admin_users.flash.cannot_change_self_role');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $roles = $user->getRoles();
        $isAdmin = in_array('ROLE_ADMIN', $roles, true);
        if ($isAdmin) {
            $user->setRoles(array_values(array_filter($roles, static fn (string $role): bool => $role !== 'ROLE_ADMIN')));
        } else {
            $roles[] = 'ROLE_ADMIN';
            $user->setRoles(array_values(array_unique($roles)));
        }
        $this->entityManager->flush();
        $this->addFlash('success', 'admin_users.flash.role_updated');

        return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
    }

    #[Route('/{id<\d+>}/reset-password', name: 'app_admin_users_reset_password', methods: ['POST'])]
    public function resetPassword(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isValidToken($request, 'admin_users_reset_password_'.$id)) {
            $this->addFlash('warning', 'admin_users.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $user = $this->userRepository->find($id);
        if (!$user instanceof UserEntity) {
            $this->addFlash('warning', 'admin_users.flash.user_not_found');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $newPassword = (string) $request->request->get('new_password', '');
        if (strlen($newPassword) < 8) {
            $this->addFlash('warning', 'admin_users.flash.password_too_short');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();
        $this->addFlash('success', 'admin_users.flash.password_updated');

        return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
    }

    private function isCurrentUser(UserEntity $candidate): bool
    {
        $current = $this->getUser();
        if (!$current instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        return $current->getId() === $candidate->getId();
    }

    private function isValidToken(Request $request, string $tokenId): bool
    {
        $token = (string) $request->request->get('_csrf_token', '');

        return $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $token));
    }
}
