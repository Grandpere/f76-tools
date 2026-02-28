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
use App\Identity\Domain\Entity\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/account-security', name: 'app_account_security', methods: ['GET'])]
final class AccountSecurityController extends AbstractController
{
    public function __construct(
        private readonly GoogleOidcIdentityReadRepository $googleOidcIdentityReadRepository,
    ) {
    }

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
}
