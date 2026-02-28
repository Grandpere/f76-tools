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

namespace App\Identity\UI\Security\Controller;

use App\Identity\Application\Oidc\GoogleOidcIdentityReadRepository;
use App\Identity\Application\Security\AuthEventLogger;
use App\Identity\Application\Security\UnlinkOwnGoogleIdentityApplicationService;
use App\Identity\Application\Security\UnlinkOwnGoogleIdentityResult;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\UI\Security\IdentityLocaleRedirector;
use App\Identity\UI\Security\UnlinkOwnGoogleIdentityFeedbackMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/account-security')]
final class AccountSecurityController extends AbstractController
{
    public function __construct(
        private readonly GoogleOidcIdentityReadRepository $googleOidcIdentityReadRepository,
        private readonly UnlinkOwnGoogleIdentityApplicationService $unlinkOwnGoogleIdentityApplicationService,
        private readonly UnlinkOwnGoogleIdentityFeedbackMapper $unlinkOwnGoogleIdentityFeedbackMapper,
        private readonly IdentityLocaleRedirector $identityLocaleRedirector,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    #[Route('', name: 'app_account_security', methods: ['GET'])]
    public function __invoke(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        $googleIdentity = $this->googleOidcIdentityReadRepository->findOneByUserAndProvider($user, 'google');

        return $this->render('security/account_security.html.twig', [
            'userEmail' => $user->getEmail(),
            'emailVerified' => $user->isEmailVerified(),
            'localPasswordEnabled' => $user->hasLocalPassword(),
            'googleLinked' => null !== $googleIdentity,
            'googleLinkedAt' => $googleIdentity?->getCreatedAt(),
        ]);
    }

    #[Route('/unlink-google', name: 'app_account_security_unlink_google', methods: ['POST'])]
    public function unlinkGoogle(Request $request): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('account_unlink_google', $submittedToken))) {
            $this->addFlash('warning', 'security.account.flash.invalid_csrf');
            $this->authEventLogger->warning('security.account.unlink_google.invalid_csrf', $user->getEmail(), $request->getClientIp(), [
                'provider' => 'google',
            ]);

            return $this->identityLocaleRedirector->toRouteWithRequestLocale($request, 'app_account_security');
        }

        $result = $this->unlinkOwnGoogleIdentityApplicationService->unlink($user);
        $feedback = $this->unlinkOwnGoogleIdentityFeedbackMapper->map($result);
        $this->addFlash($feedback['flashType'], $feedback['flashMessage']);

        if (UnlinkOwnGoogleIdentityResult::UNLINKED === $result) {
            $this->entityManager->flush();
            $this->authEventLogger->info('security.account.unlink_google.unlinked', $user->getEmail(), $request->getClientIp(), [
                'provider' => 'google',
            ]);

            return $this->identityLocaleRedirector->toRouteWithRequestLocale($request, 'app_account_security');
        }

        $this->authEventLogger->warning(sprintf('security.account.unlink_google.%s', $result->value), $user->getEmail(), $request->getClientIp(), [
            'provider' => 'google',
        ]);

        return $this->identityLocaleRedirector->toRouteWithRequestLocale($request, 'app_account_security');
    }
}
