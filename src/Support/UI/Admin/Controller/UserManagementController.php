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

namespace App\Support\UI\Admin\Controller;

use App\Entity\AdminAuditLogEntity;
use App\Entity\UserEntity;
use App\Identity\Application\Security\SignedUrlGenerator;
use App\Support\Application\AdminUser\AdminUserManagementReadRepositoryInterface;
use App\Support\Application\AdminUser\GenerateResetLinkApplicationService;
use App\Support\Application\AdminUser\GenerateResetLinkStatus;
use App\Support\Application\AdminUser\ToggleUserActiveApplicationService;
use App\Support\Application\AdminUser\ToggleUserActiveResult;
use App\Support\Application\AdminUser\ToggleUserAdminApplicationService;
use App\Support\Application\AdminUser\ToggleUserAdminResult;
use App\Support\UI\Admin\AdminAuthenticatedUserContext;
use App\Support\UI\Admin\GenerateResetLinkFeedbackMapper;
use App\Support\UI\Admin\ToggleUserActiveFeedbackMapper;
use App\Support\UI\Admin\ToggleUserAdminFeedbackMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/users')]
final class UserManagementController extends AbstractController
{
    use AdminRoleGuardControllerTrait;
    use AdminCsrfTokenValidatorTrait;

    public function __construct(
        private readonly AdminUserManagementReadRepositoryInterface $userRepository,
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
        private readonly AdminAuthenticatedUserContext $adminAuthenticatedUserContext,
    ) {
    }

    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(): Response
    {
        $this->ensureAdminAccess();

        return $this->render('admin/users.html.twig', [
            'users' => $this->userRepository->findAllOrdered(),
        ]);
    }

    #[Route('/{id<\d+>}/toggle-active', name: 'app_admin_users_toggle_active', methods: ['POST'])]
    public function toggleActive(int $id, Request $request): RedirectResponse
    {
        $failureResponse = $this->guardAdminPostOrFailure($request, 'admin_users_toggle_active_'.$id);
        if ($failureResponse instanceof RedirectResponse) {
            return $failureResponse;
        }
        $actor = $this->getAuthenticatedUser();

        $result = $this->toggleUserActiveApplicationService->toggle($id, $actor);
        $feedback = $this->toggleUserActiveFeedbackMapper->map($result);
        $this->addFlash($feedback['flashType'], $feedback['flashMessage']);

        if (ToggleUserActiveResult::UPDATED === $result) {
            $user = $this->userRepository->getById($id);
            if ($user instanceof UserEntity) {
                $this->persistAuditLog($request, $actor, 'user_toggle_active', $user, [
                    'isActive' => $user->isActive(),
                ]);
            }
            $this->entityManager->flush();
        }

        return $this->redirectToUsers($request);
    }

    #[Route('/{id<\d+>}/toggle-admin', name: 'app_admin_users_toggle_admin', methods: ['POST'])]
    public function toggleAdmin(int $id, Request $request): RedirectResponse
    {
        $failureResponse = $this->guardAdminPostOrFailure($request, 'admin_users_toggle_admin_'.$id);
        if ($failureResponse instanceof RedirectResponse) {
            return $failureResponse;
        }
        $actor = $this->getAuthenticatedUser();

        $result = $this->toggleUserAdminApplicationService->toggle($id, $actor);
        $feedback = $this->toggleUserAdminFeedbackMapper->map($result);
        $this->addFlash($feedback['flashType'], $feedback['flashMessage']);

        if (ToggleUserAdminResult::UPDATED === $result) {
            $user = $this->userRepository->getById($id);
            if ($user instanceof UserEntity) {
                $this->persistAuditLog($request, $actor, 'user_toggle_admin', $user, [
                    'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles(), true),
                ]);
            }
            $this->entityManager->flush();
        }

        return $this->redirectToUsers($request);
    }

    #[Route('/{id<\d+>}/generate-reset-link', name: 'app_admin_users_generate_reset_link', methods: ['POST'])]
    public function generateResetLink(int $id, Request $request): RedirectResponse
    {
        $failureResponse = $this->guardAdminPostOrFailure($request, 'admin_users_generate_reset_link_'.$id);
        if ($failureResponse instanceof RedirectResponse) {
            return $failureResponse;
        }
        $actor = $this->getAuthenticatedUser();

        $result = $this->generateResetLinkApplicationService->generate($id, $actor);
        $feedback = $this->generateResetLinkFeedbackMapper->map($result);

        if (is_string($feedback['auditAction'])) {
            $this->persistAuditLog($request, $actor, $feedback['auditAction'], $result->getTargetUser(), $feedback['auditContext']);
            $this->entityManager->flush();
        }

        if (GenerateResetLinkStatus::GENERATED === $result->getStatus() && is_string($result->getToken())) {
            $resetUrl = $this->signedUrlGenerator->generate('app_reset_password', [
                'locale' => $request->getLocale(),
                'token' => $result->getToken(),
            ]);

            $translated = $this->translator->trans($feedback['flashMessage'], $feedback['flashParams']);
            $this->addFlash($feedback['flashType'], sprintf('%s %s', $translated, $resetUrl));

            return $this->redirectToUsers($request);
        }

        $this->addFlash(
            $feedback['flashType'],
            $this->translator->trans($feedback['flashMessage'], $feedback['flashParams']),
        );

        return $this->redirectToUsers($request);
    }

    private function guardAdminPostOrFailure(Request $request, string $tokenId): ?RedirectResponse
    {
        $this->ensureAdminAccess();
        if ($this->isValidToken($request, $tokenId)) {
            return null;
        }

        $this->addFlash('warning', 'admin_users.flash.invalid_csrf');

        return $this->redirectToUsers($request);
    }

    private function redirectToUsers(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('app_admin_users', ['locale' => $request->getLocale()]);
    }

    /**
     * @param array<string, mixed>|null $context
     */
    private function persistAuditLog(Request $request, UserEntity $actor, string $action, ?UserEntity $targetUser, ?array $context = null): void
    {
        $payload = [
            'ip' => $request->getClientIp(),
            'locale' => $request->getLocale(),
        ];

        if (is_array($context)) {
            $payload = array_merge($payload, $context);
        }

        $auditLog = new AdminAuditLogEntity()
            ->setActorUser($actor)
            ->setTargetUser($targetUser)
            ->setAction($action)
            ->setContext($payload);

        $this->entityManager->persist($auditLog);
    }

    private function getAuthenticatedUser(): UserEntity
    {
        return $this->adminAuthenticatedUserContext->requireAuthenticatedUser($this->getUser());
    }

    protected function csrfTokenManager(): CsrfTokenManagerInterface
    {
        return $this->csrfTokenManager;
    }
}
