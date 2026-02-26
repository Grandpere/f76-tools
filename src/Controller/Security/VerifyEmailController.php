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
use App\Security\SignedUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyEmailController extends AbstractController
{
    public function __construct(
        private readonly UserEntityRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SignedUrlGenerator $signedUrlGenerator,
    ) {
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function __invoke(string $token, Request $request): RedirectResponse
    {
        if (!$this->signedUrlGenerator->isRequestSignatureValid($request)) {
            $this->addFlash('warning', 'security.verify.flash.invalid_or_expired');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        $user = $this->resolveValidUserByToken($token);
        if (!$user instanceof UserEntity) {
            $this->addFlash('warning', 'security.verify.flash.invalid_or_expired');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        $user->setIsEmailVerified(true);
        $user->setEmailVerificationTokenHash(null);
        $user->setEmailVerificationExpiresAt(null);
        $user->setEmailVerificationRequestedAt(null);
        $this->entityManager->flush();

        $this->addFlash('success', 'security.verify.flash.success');

        return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
    }

    private function resolveValidUserByToken(string $token): ?UserEntity
    {
        if ('' === trim($token)) {
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->userRepository->findOneByEmailVerificationTokenHash($tokenHash);
        if (!$user instanceof UserEntity) {
            return null;
        }

        $expiresAt = $user->getEmailVerificationExpiresAt();
        if (null === $expiresAt || $expiresAt < new \DateTimeImmutable()) {
            return null;
        }

        return $user;
    }
}
