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

use App\Identity\Application\ChangePassword\ChangePasswordApplicationService;
use App\Identity\Application\ChangePassword\ChangePasswordRequest;
use App\Identity\Application\ChangePassword\ChangePasswordResult;
use App\Identity\Application\Security\AuthEventLogger;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\UI\Security\ChangePasswordFeedbackMapper;
use App\Identity\UI\Security\IdentityFlashResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/change-password', name: 'app_change_password', methods: ['GET', 'POST'])]
final class ChangePasswordController extends AbstractController
{
    public function __construct(
        private readonly ChangePasswordApplicationService $changePasswordApplicationService,
        private readonly ChangePasswordFeedbackMapper $changePasswordFeedbackMapper,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly AuthEventLogger $authEventLogger,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('change_password', $csrfToken))) {
                $this->authEventLogger->warning('security.auth.change_password.invalid_csrf', $user->getEmail(), $request->getClientIp());

                return $this->identityFlashResponder->flashToCurrentUri($request, 'warning', 'security.change_password.flash.invalid_csrf');
            }

            $result = $this->changePasswordApplicationService->change($user, ChangePasswordRequest::fromRaw(
                (string) $request->request->get('current_password', ''),
                (string) $request->request->get('new_password', ''),
                (string) $request->request->get('new_password_confirm', ''),
            ));
            $feedback = $this->changePasswordFeedbackMapper->map($result);

            if (ChangePasswordResult::SUCCESS === $result) {
                $this->authEventLogger->info('security.auth.change_password.success', $user->getEmail(), $request->getClientIp());
            } else {
                $this->authEventLogger->warning('security.auth.change_password.failed', $user->getEmail(), $request->getClientIp(), [
                    'reason' => $result->value,
                ]);
            }

            return $this->identityFlashResponder->flashToCurrentUri($request, $feedback['flashType'], $feedback['flashMessage']);
        }

        return $this->render('security/change_password.html.twig');
    }
}
