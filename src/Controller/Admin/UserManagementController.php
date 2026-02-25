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

use App\Entity\AdminAuditLogEntity;
use App\Entity\UserEntity;
use App\Repository\UserEntityRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/users')]
final class UserManagementController extends AbstractController
{
    public function __construct(
        private readonly UserEntityRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
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
        $this->persistAuditLog($request, 'user_toggle_active', $user, [
            'isActive' => $user->isActive(),
        ]);
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
        $this->persistAuditLog($request, 'user_toggle_admin', $user, [
            'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles(), true),
        ]);
        $this->entityManager->flush();
        $this->addFlash('success', 'admin_users.flash.role_updated');

        return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
    }

    #[Route('/{id<\d+>}/generate-reset-link', name: 'app_admin_users_generate_reset_link', methods: ['POST'])]
    public function generateResetLink(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isValidToken($request, 'admin_users_generate_reset_link_'.$id)) {
            $this->addFlash('warning', 'admin_users.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $user = $this->userRepository->find($id);
        if (!$user instanceof UserEntity) {
            $this->addFlash('warning', 'admin_users.flash.user_not_found');

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT2H'));
        $user->setResetPasswordTokenHash($tokenHash);
        $user->setResetPasswordExpiresAt($expiresAt);
        $this->persistAuditLog($request, 'user_generate_reset_link', $user, [
            'expiresAt' => $expiresAt->format(\DateTimeInterface::ATOM),
        ]);
        $this->entityManager->flush();

        $resetUrl = $this->urlGenerator->generate('app_reset_password', [
            'locale' => $request->getLocale(),
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $translated = $this->translator->trans('admin_users.flash.reset_link_generated');
        $this->addFlash('success', sprintf('%s %s', $translated, $resetUrl));

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

    /**
     * @param array<string, mixed>|null $context
     */
    private function persistAuditLog(Request $request, string $action, ?UserEntity $targetUser, ?array $context = null): void
    {
        $actor = $this->getUser();
        if (!$actor instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        $payload = [
            'ip' => $request->getClientIp(),
            'locale' => $request->getLocale(),
        ];

        if (is_array($context)) {
            $payload = array_merge($payload, $context);
        }

        $auditLog = (new AdminAuditLogEntity())
            ->setActorUser($actor)
            ->setTargetUser($targetUser)
            ->setAction($action)
            ->setContext($payload);

        $this->entityManager->persist($auditLog);
    }
}
