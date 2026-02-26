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
use App\Security\SignedUrlGenerator;
use App\Support\Application\AdminUser\GenerateResetLinkApplicationService;
use App\Support\Application\AdminUser\GenerateResetLinkStatus;
use App\Support\Application\AdminUser\ToggleUserActiveApplicationService;
use App\Support\Application\AdminUser\ToggleUserActiveResult;
use App\Support\Application\AdminUser\ToggleUserAdminApplicationService;
use App\Support\Application\AdminUser\ToggleUserAdminResult;
use App\Support\UI\Admin\GenerateResetLinkFeedbackMapper;
use App\Support\UI\Admin\ToggleUserAdminFeedbackMapper;
use App\Support\UI\Admin\ToggleUserActiveFeedbackMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
        private readonly SignedUrlGenerator $signedUrlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly ToggleUserActiveApplicationService $toggleUserActiveApplicationService,
        private readonly ToggleUserActiveFeedbackMapper $toggleUserActiveFeedbackMapper,
        private readonly ToggleUserAdminApplicationService $toggleUserAdminApplicationService,
        private readonly ToggleUserAdminFeedbackMapper $toggleUserAdminFeedbackMapper,
        private readonly GenerateResetLinkApplicationService $generateResetLinkApplicationService,
        private readonly GenerateResetLinkFeedbackMapper $generateResetLinkFeedbackMapper,
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

        $result = $this->toggleUserActiveApplicationService->toggle($id, $this->getUser());
        $feedback = $this->toggleUserActiveFeedbackMapper->map($result);
        $this->addFlash($feedback['flashType'], $feedback['flashMessage']);

        if (ToggleUserActiveResult::UPDATED === $result) {
            $user = $this->userRepository->getById($id);
            if ($user instanceof UserEntity) {
                $this->persistAuditLog($request, 'user_toggle_active', $user, [
                    'isActive' => $user->isActive(),
                ]);
            }
            $this->entityManager->flush();
        }

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

        $result = $this->toggleUserAdminApplicationService->toggle($id, $this->getUser());
        $feedback = $this->toggleUserAdminFeedbackMapper->map($result);
        $this->addFlash($feedback['flashType'], $feedback['flashMessage']);

        if (ToggleUserAdminResult::UPDATED === $result) {
            $user = $this->userRepository->getById($id);
            if ($user instanceof UserEntity) {
                $this->persistAuditLog($request, 'user_toggle_admin', $user, [
                    'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles(), true),
                ]);
            }
            $this->entityManager->flush();
        }

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

        $result = $this->generateResetLinkApplicationService->generate($id, $this->getUser());
        $feedback = $this->generateResetLinkFeedbackMapper->map($result);

        if (is_string($feedback['auditAction'])) {
            $this->persistAuditLog($request, $feedback['auditAction'], $result->getTargetUser(), $feedback['auditContext']);
            $this->entityManager->flush();
        }

        if (GenerateResetLinkStatus::GENERATED === $result->getStatus() && is_string($result->getToken())) {
            $resetUrl = $this->signedUrlGenerator->generate('app_reset_password', [
                'locale' => $request->getLocale(),
                'token' => $result->getToken(),
            ]);

            $translated = $this->translator->trans($feedback['flashMessage'], $feedback['flashParams']);
            $this->addFlash($feedback['flashType'], sprintf('%s %s', $translated, $resetUrl));

            return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
        }

        $this->addFlash(
            $feedback['flashType'],
            $this->translator->trans($feedback['flashMessage'], $feedback['flashParams']),
        );

        return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
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
