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

use App\Identity\Application\Security\AuthAuditLogReader;
use App\Identity\Application\Security\SignedUrlGenerator;
use App\Identity\Domain\Entity\UserEntity;
use App\Support\Application\AdminUser\AdminUserGoogleIdentityReadService;
use App\Support\Application\AdminUser\AdminUserManagementReadRepositoryInterface;
use App\Support\Application\AdminUser\ForceVerifyEmailApplicationService;
use App\Support\Application\AdminUser\ForceVerifyEmailResult;
use App\Support\Application\AdminUser\GenerateResetLinkApplicationService;
use App\Support\Application\AdminUser\GenerateResetLinkStatus;
use App\Support\Application\AdminUser\ResendVerificationEmailApplicationService;
use App\Support\Application\AdminUser\ResendVerificationEmailStatus;
use App\Support\Application\AdminUser\ToggleUserActiveApplicationService;
use App\Support\Application\AdminUser\ToggleUserActiveResult;
use App\Support\Application\AdminUser\ToggleUserAdminApplicationService;
use App\Support\Application\AdminUser\ToggleUserAdminResult;
use App\Support\Application\AdminUser\UnlinkGoogleIdentityApplicationService;
use App\Support\Application\AdminUser\UnlinkGoogleIdentityResult;
use App\Support\Domain\Entity\AdminAuditLogEntity;
use App\Support\UI\Admin\AdminAuthenticatedUserContext;
use App\Support\UI\Admin\ForceVerifyEmailFeedbackMapper;
use App\Support\UI\Admin\GenerateResetLinkFeedbackMapper;
use App\Support\UI\Admin\ResendVerificationEmailFeedbackMapper;
use App\Support\UI\Admin\ToggleUserActiveFeedbackMapper;
use App\Support\UI\Admin\ToggleUserAdminFeedbackMapper;
use App\Support\UI\Admin\UnlinkGoogleIdentityFeedbackMapper;
use DateTimeImmutable;
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
        private readonly AdminUserGoogleIdentityReadService $adminUserGoogleIdentityReadService,
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
        private readonly ForceVerifyEmailApplicationService $forceVerifyEmailApplicationService,
        private readonly ForceVerifyEmailFeedbackMapper $forceVerifyEmailFeedbackMapper,
        private readonly ResendVerificationEmailApplicationService $resendVerificationEmailApplicationService,
        private readonly ResendVerificationEmailFeedbackMapper $resendVerificationEmailFeedbackMapper,
        private readonly UnlinkGoogleIdentityApplicationService $unlinkGoogleIdentityApplicationService,
        private readonly UnlinkGoogleIdentityFeedbackMapper $unlinkGoogleIdentityFeedbackMapper,
        private readonly AdminAuthenticatedUserContext $adminAuthenticatedUserContext,
        private readonly \App\Identity\UI\Security\IdentityIssuedTokenNotifier $identityIssuedTokenNotifier,
        private readonly AuthAuditLogReader $authAuditLogReader,
    ) {
    }

    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->ensureAdminAccess();
        $users = $this->userRepository->findAllOrdered();
        $googleIdentitiesByUserId = $this->adminUserGoogleIdentityReadService->getGoogleIdentityByUserId($users);
        $googleFilter = $this->normalizeGoogleFilter($request->query->getString('google', ''));
        $activeFilter = $this->normalizeActiveFilter($request->query->getString('active', ''));
        $roleFilter = $this->normalizeRoleFilter($request->query->getString('role', ''));
        $verifiedFilter = $this->normalizeVerifiedFilter($request->query->getString('verified', ''));
        $localPasswordFilter = $this->normalizeLocalPasswordFilter($request->query->getString('localPassword', ''));
        $createdFrom = $this->normalizeDateFilter($request->query->getString('createdFrom', ''));
        $createdTo = $this->normalizeDateFilter($request->query->getString('createdTo', ''));
        $query = trim($request->query->getString('q', ''));
        $sort = $this->normalizeSort($request->query->getString('sort', ''));
        $dir = $this->normalizeSortDirection($request->query->getString('dir', ''));
        $perPage = $this->normalizePerPage((int) $request->query->get('perPage', 30));
        $page = $this->normalizePage((int) $request->query->get('page', 1));
        $filteredUsers = $this->filterUsersByActiveStatus($users, $activeFilter);
        $filteredUsers = $this->filterUsersByGoogleIdentity($filteredUsers, $googleIdentitiesByUserId, $googleFilter);
        $filteredUsers = $this->filterUsersByRole($filteredUsers, $roleFilter);
        $filteredUsers = $this->filterUsersByVerificationStatus($filteredUsers, $verifiedFilter);
        $filteredUsers = $this->filterUsersByLocalPasswordStatus($filteredUsers, $localPasswordFilter);
        $filteredUsers = $this->filterUsersByCreatedAtRange(
            $filteredUsers,
            $this->parseDateStartOfDay($createdFrom),
            $this->parseDateEndOfDay($createdTo),
        );
        $filteredUsers = $this->filterUsersBySearchQuery($filteredUsers, $query);
        $filteredUsers = $this->sortUsers($filteredUsers, $sort, $dir);
        $totalUsers = count($users);
        $googleLinkedCount = count($googleIdentitiesByUserId);
        $googleUnlinkedCount = max(0, $totalUsers - $googleLinkedCount);
        $visibleUsers = count($filteredUsers);
        $totalPages = max(1, (int) ceil($visibleUsers / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $paginatedUsers = array_slice($filteredUsers, $offset, $perPage);

        return $this->render('admin/users.html.twig', [
            'users' => $paginatedUsers,
            'googleIdentitiesByUserId' => $googleIdentitiesByUserId,
            'googleFilter' => $googleFilter,
            'activeFilter' => $activeFilter,
            'roleFilter' => $roleFilter,
            'verifiedFilter' => $verifiedFilter,
            'localPasswordFilter' => $localPasswordFilter,
            'createdFrom' => $createdFrom,
            'createdTo' => $createdTo,
            'query' => $query,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'totalUsers' => $totalUsers,
            'googleLinkedCount' => $googleLinkedCount,
            'googleUnlinkedCount' => $googleUnlinkedCount,
            'visibleUsers' => $visibleUsers,
        ]);
    }

    #[Route('/export', name: 'app_admin_users_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): Response
    {
        $this->ensureAdminAccess();
        $users = $this->userRepository->findAllOrdered();
        $googleIdentitiesByUserId = $this->adminUserGoogleIdentityReadService->getGoogleIdentityByUserId($users);
        $googleFilter = $this->normalizeGoogleFilter($request->query->getString('google', ''));
        $activeFilter = $this->normalizeActiveFilter($request->query->getString('active', ''));
        $roleFilter = $this->normalizeRoleFilter($request->query->getString('role', ''));
        $verifiedFilter = $this->normalizeVerifiedFilter($request->query->getString('verified', ''));
        $localPasswordFilter = $this->normalizeLocalPasswordFilter($request->query->getString('localPassword', ''));
        $createdFrom = $this->normalizeDateFilter($request->query->getString('createdFrom', ''));
        $createdTo = $this->normalizeDateFilter($request->query->getString('createdTo', ''));
        $query = trim($request->query->getString('q', ''));
        $sort = $this->normalizeSort($request->query->getString('sort', ''));
        $dir = $this->normalizeSortDirection($request->query->getString('dir', ''));

        $filteredUsers = $this->filterUsersByActiveStatus($users, $activeFilter);
        $filteredUsers = $this->filterUsersByGoogleIdentity($filteredUsers, $googleIdentitiesByUserId, $googleFilter);
        $filteredUsers = $this->filterUsersByRole($filteredUsers, $roleFilter);
        $filteredUsers = $this->filterUsersByVerificationStatus($filteredUsers, $verifiedFilter);
        $filteredUsers = $this->filterUsersByLocalPasswordStatus($filteredUsers, $localPasswordFilter);
        $filteredUsers = $this->filterUsersByCreatedAtRange(
            $filteredUsers,
            $this->parseDateStartOfDay($createdFrom),
            $this->parseDateEndOfDay($createdTo),
        );
        $filteredUsers = $this->filterUsersBySearchQuery($filteredUsers, $query);
        $filteredUsers = $this->sortUsers($filteredUsers, $sort, $dir);

        $filename = sprintf('admin-users-%s.csv', new DateTimeImmutable()->format('Ymd-His'));
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            return new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        fputcsv($handle, [
            'email',
            'created_at',
            'is_active',
            'is_email_verified',
            'has_local_password',
            'roles',
            'google_linked',
            'google_linked_since',
        ], ',', '"', '');

        foreach ($filteredUsers as $user) {
            $userId = $user->getId();
            $googleIdentity = is_int($userId) ? ($googleIdentitiesByUserId[$userId] ?? null) : null;
            $googleLinked = null !== $googleIdentity;

            fputcsv($handle, [
                $this->sanitizeCsvValue($user->getEmail()),
                $this->sanitizeCsvValue($user->getCreatedAt()->format('Y-m-d H:i:s')),
                $this->sanitizeCsvValue($user->isActive() ? '1' : '0'),
                $this->sanitizeCsvValue($user->isEmailVerified() ? '1' : '0'),
                $this->sanitizeCsvValue($user->hasLocalPassword() ? '1' : '0'),
                $this->sanitizeCsvValue(implode('|', $user->getRoles())),
                $this->sanitizeCsvValue($googleLinked ? '1' : '0'),
                $this->sanitizeCsvValue($googleLinked ? $googleIdentity->getCreatedAt()->format('Y-m-d H:i:s') : ''),
            ], ',', '"', '');
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        $response = new Response("\xEF\xBB\xBF".(is_string($csvContent) ? $csvContent : ''));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    #[Route('/{id<\d+>}/auth-events', name: 'app_admin_users_auth_events', methods: ['GET'])]
    public function authEvents(int $id, Request $request): Response
    {
        $this->ensureAdminAccess();

        $targetUser = $this->userRepository->getById($id);
        if (!$targetUser instanceof UserEntity) {
            throw $this->createNotFoundException('User not found.');
        }

        $level = $this->normalizeAuthEventLevel($request->query->getString('level', ''));
        $query = trim($request->query->getString('q', ''));
        $events = $this->authAuditLogReader->findByUserIdWithFilters($id, 60, $level, $query);
        $backParams = $this->usersListQueryParamsFromRequest($request);
        $localeHiddenFields = $backParams;
        unset($localeHiddenFields['locale']);

        return $this->render('admin/user_auth_events.html.twig', [
            'targetUser' => $targetUser,
            'events' => $events,
            'backParams' => $backParams,
            'localeHiddenFields' => $localeHiddenFields,
            'levelFilter' => $level,
            'query' => $query,
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

    #[Route('/{id<\d+>}/unlink-google', name: 'app_admin_users_unlink_google', methods: ['POST'])]
    public function unlinkGoogleIdentity(int $id, Request $request): RedirectResponse
    {
        $failureResponse = $this->guardAdminPostOrFailure($request, 'admin_users_unlink_google_'.$id);
        if ($failureResponse instanceof RedirectResponse) {
            return $failureResponse;
        }
        $actor = $this->getAuthenticatedUser();

        $result = $this->unlinkGoogleIdentityApplicationService->unlink($id);
        $feedback = $this->unlinkGoogleIdentityFeedbackMapper->map($result);
        $this->addFlash($feedback['flashType'], $feedback['flashMessage']);

        if (UnlinkGoogleIdentityResult::UNLINKED === $result) {
            $user = $this->userRepository->getById($id);
            if ($user instanceof UserEntity) {
                $this->persistAuditLog($request, $actor, 'user_unlink_google_identity', $user, [
                    'provider' => 'google',
                ]);
            } else {
                $this->persistAuditLog($request, $actor, 'user_unlink_google_identity', null, [
                    'provider' => 'google',
                    'targetUserId' => $id,
                ]);
            }
            $this->entityManager->flush();
        }

        return $this->redirectToUsers($request);
    }

    #[Route('/{id<\d+>}/resend-verification', name: 'app_admin_users_resend_verification', methods: ['POST'])]
    public function resendVerification(int $id, Request $request): RedirectResponse
    {
        $failureResponse = $this->guardAdminPostOrFailure($request, 'admin_users_resend_verification_'.$id);
        if ($failureResponse instanceof RedirectResponse) {
            return $failureResponse;
        }
        $actor = $this->getAuthenticatedUser();

        $result = $this->resendVerificationEmailApplicationService->request($id);
        $feedback = $this->resendVerificationEmailFeedbackMapper->map($result);
        $this->addFlash(
            $feedback['flashType'],
            $this->translator->trans($feedback['flashMessage'], $feedback['flashParams']),
        );

        if (ResendVerificationEmailStatus::GENERATED === $result->status()) {
            $this->identityIssuedTokenNotifier->notifyVerification(
                $result->targetUser()?->getEmail(),
                $result->plainToken(),
                $request->getLocale(),
                $request->getClientIp(),
                'security.auth.admin.resend_verification.token_issued',
            );
        }

        if (is_string($feedback['auditAction'])) {
            $this->persistAuditLog($request, $actor, $feedback['auditAction'], $result->targetUser(), $feedback['auditContext']);
            $this->entityManager->flush();
        }

        return $this->redirectToUsers($request);
    }

    #[Route('/{id<\d+>}/force-verify-email', name: 'app_admin_users_force_verify_email', methods: ['POST'])]
    public function forceVerifyEmail(int $id, Request $request): RedirectResponse
    {
        $failureResponse = $this->guardAdminPostOrFailure($request, 'admin_users_force_verify_email_'.$id);
        if ($failureResponse instanceof RedirectResponse) {
            return $failureResponse;
        }
        $actor = $this->getAuthenticatedUser();

        $result = $this->forceVerifyEmailApplicationService->verify($id);
        $feedback = $this->forceVerifyEmailFeedbackMapper->map($result);
        $this->addFlash($feedback['flashType'], $feedback['flashMessage']);

        if (is_string($feedback['auditAction'])) {
            $targetUser = $this->userRepository->getById($id);
            $this->persistAuditLog($request, $actor, $feedback['auditAction'], $targetUser, null);
            $this->entityManager->flush();
        } elseif (ForceVerifyEmailResult::VERIFIED === $result) {
            $this->entityManager->flush();
        }

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
        return $this->redirectToRoute('app_admin_users', $this->usersListPostParamsFromRequest($request));
    }

    /**
     * @return array{locale: string, google: string, active: string, role: string, verified: string, localPassword: string, createdFrom: string, createdTo: string, q: string, sort: string, dir: string, perPage: int, page: int}
     */
    private function usersListPostParamsFromRequest(Request $request): array
    {
        return [
            'locale' => $request->getLocale(),
            'google' => $this->normalizeGoogleFilter((string) $request->request->get('google', '')),
            'active' => $this->normalizeActiveFilter((string) $request->request->get('active', '')),
            'role' => $this->normalizeRoleFilter((string) $request->request->get('role', '')),
            'verified' => $this->normalizeVerifiedFilter((string) $request->request->get('verified', '')),
            'localPassword' => $this->normalizeLocalPasswordFilter((string) $request->request->get('localPassword', '')),
            'createdFrom' => $this->normalizeDateFilter((string) $request->request->get('createdFrom', '')),
            'createdTo' => $this->normalizeDateFilter((string) $request->request->get('createdTo', '')),
            'q' => trim((string) $request->request->get('q', '')),
            'sort' => $this->normalizeSort((string) $request->request->get('sort', '')),
            'dir' => $this->normalizeSortDirection((string) $request->request->get('dir', '')),
            'perPage' => $this->normalizePerPage((int) $request->request->get('perPage', 30)),
            'page' => $this->normalizePage((int) $request->request->get('page', 1)),
        ];
    }

    /**
     * @return array{locale: string, google: string, active: string, role: string, verified: string, localPassword: string, createdFrom: string, createdTo: string, q: string, sort: string, dir: string, perPage: int, page: int}
     */
    private function usersListQueryParamsFromRequest(Request $request): array
    {
        return [
            'locale' => $request->getLocale(),
            'google' => $this->normalizeGoogleFilter($request->query->getString('google', '')),
            'active' => $this->normalizeActiveFilter($request->query->getString('active', '')),
            'role' => $this->normalizeRoleFilter($request->query->getString('role', '')),
            'verified' => $this->normalizeVerifiedFilter($request->query->getString('verified', '')),
            'localPassword' => $this->normalizeLocalPasswordFilter($request->query->getString('localPassword', '')),
            'createdFrom' => $this->normalizeDateFilter($request->query->getString('createdFrom', '')),
            'createdTo' => $this->normalizeDateFilter($request->query->getString('createdTo', '')),
            'q' => trim($request->query->getString('q', '')),
            'sort' => $this->normalizeSort($request->query->getString('sort', '')),
            'dir' => $this->normalizeSortDirection($request->query->getString('dir', '')),
            'perPage' => $this->normalizePerPage((int) $request->query->get('perPage', 30)),
            'page' => $this->normalizePage((int) $request->query->get('page', 1)),
        ];
    }

    /**
     * @param array<string, bool|int|string|null>|null $context
     */
    private function persistAuditLog(Request $request, UserEntity $actor, string $action, ?UserEntity $targetUser, ?array $context = null): void
    {
        /** @var array<string, bool|int|string|null> $payload */
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

    /**
     * @param list<UserEntity>                                           $users
     * @param array<int, \App\Identity\Domain\Entity\UserIdentityEntity> $googleIdentitiesByUserId
     *
     * @return list<UserEntity>
     */
    private function filterUsersByGoogleIdentity(array $users, array $googleIdentitiesByUserId, string $googleFilter): array
    {
        if ('linked' !== $googleFilter && 'unlinked' !== $googleFilter) {
            return $users;
        }

        $filtered = [];
        foreach ($users as $user) {
            $userId = $user->getId();
            if (!is_int($userId)) {
                continue;
            }

            $isLinked = array_key_exists($userId, $googleIdentitiesByUserId);
            if ('linked' === $googleFilter && !$isLinked) {
                continue;
            }
            if ('unlinked' === $googleFilter && $isLinked) {
                continue;
            }

            $filtered[] = $user;
        }

        return $filtered;
    }

    private function normalizeGoogleFilter(string $googleFilter): string
    {
        $normalized = mb_strtolower(trim($googleFilter));

        return in_array($normalized, ['linked', 'unlinked'], true) ? $normalized : '';
    }

    private function normalizeActiveFilter(string $activeFilter): string
    {
        $normalized = mb_strtolower(trim($activeFilter));

        return in_array($normalized, ['active', 'inactive'], true) ? $normalized : '';
    }

    private function normalizeRoleFilter(string $roleFilter): string
    {
        $normalized = mb_strtolower(trim($roleFilter));

        return in_array($normalized, ['admin', 'user'], true) ? $normalized : '';
    }

    private function normalizeVerifiedFilter(string $verifiedFilter): string
    {
        $normalized = mb_strtolower(trim($verifiedFilter));

        return in_array($normalized, ['verified', 'unverified'], true) ? $normalized : '';
    }

    private function normalizeLocalPasswordFilter(string $localPasswordFilter): string
    {
        $normalized = mb_strtolower(trim($localPasswordFilter));

        return in_array($normalized, ['enabled', 'disabled'], true) ? $normalized : '';
    }

    private function normalizeDateFilter(string $date): string
    {
        $normalized = trim($date);
        if ('' === $normalized) {
            return '';
        }

        return null !== $this->parseDate($normalized) ? $normalized : '';
    }

    /**
     * @param list<UserEntity> $users
     *
     * @return list<UserEntity>
     */
    private function filterUsersByActiveStatus(array $users, string $activeFilter): array
    {
        if ('active' !== $activeFilter && 'inactive' !== $activeFilter) {
            return $users;
        }

        $filtered = [];
        foreach ($users as $user) {
            if ('active' === $activeFilter && !$user->isActive()) {
                continue;
            }
            if ('inactive' === $activeFilter && $user->isActive()) {
                continue;
            }
            $filtered[] = $user;
        }

        return $filtered;
    }

    /**
     * @param list<UserEntity> $users
     *
     * @return list<UserEntity>
     */
    private function filterUsersByVerificationStatus(array $users, string $verifiedFilter): array
    {
        if ('verified' !== $verifiedFilter && 'unverified' !== $verifiedFilter) {
            return $users;
        }

        $filtered = [];
        foreach ($users as $user) {
            if ('verified' === $verifiedFilter && !$user->isEmailVerified()) {
                continue;
            }
            if ('unverified' === $verifiedFilter && $user->isEmailVerified()) {
                continue;
            }
            $filtered[] = $user;
        }

        return $filtered;
    }

    /**
     * @param list<UserEntity> $users
     *
     * @return list<UserEntity>
     */
    private function filterUsersByRole(array $users, string $roleFilter): array
    {
        if ('admin' !== $roleFilter && 'user' !== $roleFilter) {
            return $users;
        }

        $filtered = [];
        foreach ($users as $user) {
            $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
            if ('admin' === $roleFilter && !$isAdmin) {
                continue;
            }
            if ('user' === $roleFilter && $isAdmin) {
                continue;
            }
            $filtered[] = $user;
        }

        return $filtered;
    }

    /**
     * @param list<UserEntity> $users
     *
     * @return list<UserEntity>
     */
    private function filterUsersByLocalPasswordStatus(array $users, string $localPasswordFilter): array
    {
        if ('enabled' !== $localPasswordFilter && 'disabled' !== $localPasswordFilter) {
            return $users;
        }

        $filtered = [];
        foreach ($users as $user) {
            if ('enabled' === $localPasswordFilter && !$user->hasLocalPassword()) {
                continue;
            }
            if ('disabled' === $localPasswordFilter && $user->hasLocalPassword()) {
                continue;
            }
            $filtered[] = $user;
        }

        return $filtered;
    }

    /**
     * @param list<UserEntity> $users
     *
     * @return list<UserEntity>
     */
    private function filterUsersByCreatedAtRange(array $users, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        if (!$from instanceof DateTimeImmutable && !$to instanceof DateTimeImmutable) {
            return $users;
        }

        $filtered = [];
        foreach ($users as $user) {
            $createdAt = $user->getCreatedAt();
            if ($from instanceof DateTimeImmutable && $createdAt < $from) {
                continue;
            }
            if ($to instanceof DateTimeImmutable && $createdAt > $to) {
                continue;
            }
            $filtered[] = $user;
        }

        return $filtered;
    }

    /**
     * @param list<UserEntity> $users
     *
     * @return list<UserEntity>
     */
    private function filterUsersBySearchQuery(array $users, string $query): array
    {
        if ('' === $query) {
            return $users;
        }

        $normalizedQuery = mb_strtolower($query);
        $filtered = [];
        foreach ($users as $user) {
            if (str_contains(mb_strtolower($user->getEmail()), $normalizedQuery)) {
                $filtered[] = $user;
            }
        }

        return $filtered;
    }

    private function normalizePerPage(int $perPage): int
    {
        $allowed = [20, 30, 50, 100];

        return in_array($perPage, $allowed, true) ? $perPage : 30;
    }

    private function normalizePage(int $page): int
    {
        return max(1, $page);
    }

    private function normalizeSort(string $sort): string
    {
        $normalized = mb_strtolower(trim($sort));

        return in_array($normalized, ['email', 'createdat', 'active'], true) ? $normalized : 'email';
    }

    private function normalizeSortDirection(string $dir): string
    {
        $normalized = mb_strtolower(trim($dir));

        return in_array($normalized, ['asc', 'desc'], true) ? $normalized : 'asc';
    }

    private function normalizeAuthEventLevel(string $level): string
    {
        $normalized = mb_strtolower(trim($level));

        return in_array($normalized, ['info', 'warning'], true) ? $normalized : '';
    }

    private function parseDate(string $date): ?DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return false === $parsed ? null : $parsed;
    }

    private function parseDateStartOfDay(string $date): ?DateTimeImmutable
    {
        $parsed = $this->parseDate($date);
        if (!$parsed instanceof DateTimeImmutable) {
            return null;
        }

        return $parsed->setTime(0, 0, 0);
    }

    private function parseDateEndOfDay(string $date): ?DateTimeImmutable
    {
        $parsed = $this->parseDate($date);
        if (!$parsed instanceof DateTimeImmutable) {
            return null;
        }

        return $parsed->setTime(23, 59, 59);
    }

    private function sanitizeCsvValue(string $value): string
    {
        if ('' === $value) {
            return $value;
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param list<UserEntity> $users
     *
     * @return list<UserEntity>
     */
    private function sortUsers(array $users, string $sort, string $dir): array
    {
        usort($users, static function (UserEntity $left, UserEntity $right) use ($sort, $dir): int {
            $comparison = match ($sort) {
                'createdat' => $left->getCreatedAt() <=> $right->getCreatedAt(),
                'active' => ((int) $left->isActive()) <=> ((int) $right->isActive()),
                default => strcmp($left->getEmail(), $right->getEmail()),
            };

            return 'desc' === $dir ? -$comparison : $comparison;
        });

        return $users;
    }
}
