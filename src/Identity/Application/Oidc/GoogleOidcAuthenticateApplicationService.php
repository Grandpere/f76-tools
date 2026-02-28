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

namespace App\Identity\Application\Oidc;

use App\Identity\Application\Common\IdentityPasswordHasher;
use App\Identity\Application\Common\IdentityWritePersistence;
use App\Identity\Application\User\UserByEmailFinder;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;

final class GoogleOidcAuthenticateApplicationService
{
    private const PROVIDER = 'google';

    public function __construct(
        private readonly GoogleOidcIdentityReadRepository $identityReadRepository,
        private readonly GoogleOidcIdentityWriteRepository $identityWriteRepository,
        private readonly UserByEmailFinder $userByEmailFinder,
        private readonly IdentityPasswordHasher $passwordHasher,
        private readonly IdentityWritePersistence $persistence,
    ) {
    }

    public function authenticate(GoogleOidcUserProfile $profile): GoogleOidcAuthenticateResult
    {
        $providerUserId = trim($profile->providerUserId());
        if ('' === $providerUserId) {
            throw new GoogleOidcAuthenticationException('security.oidc.flash.callback_failed');
        }

        $email = mb_strtolower(trim($profile->email()));
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new GoogleOidcAuthenticationException('security.oidc.flash.callback_failed');
        }

        if (!$profile->emailVerified()) {
            throw new GoogleOidcAuthenticationException('security.oidc.flash.email_not_verified');
        }

        $existingIdentity = $this->identityReadRepository->findOneByProviderAndProviderUserId(self::PROVIDER, $providerUserId);
        if ($existingIdentity instanceof UserIdentityEntity) {
            $user = $existingIdentity->getUser();
            $this->markEmailVerified($user);
            $this->persistence->flush();

            return new GoogleOidcAuthenticateResult($user, GoogleOidcAuthenticationAction::IDENTITY_FOUND);
        }

        $existingUser = $this->userByEmailFinder->findOneByEmail($email);
        if ($existingUser instanceof UserEntity) {
            $identityForUser = $this->identityReadRepository->findOneByUserAndProvider($existingUser, self::PROVIDER);
            if ($identityForUser instanceof UserIdentityEntity) {
                throw new GoogleOidcAuthenticationException('security.oidc.flash.account_link_conflict');
            }

            $this->markEmailVerified($existingUser);
            $this->identityWriteRepository->save($this->newIdentity($existingUser, $providerUserId, $email));
            $this->persistence->flush();

            return new GoogleOidcAuthenticateResult($existingUser, GoogleOidcAuthenticationAction::AUTO_LINKED);
        }

        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setIsEmailVerified(true)
            ->setHasLocalPassword(false);
        $user->setPassword($this->passwordHasher->hash($user, bin2hex(random_bytes(32))));
        $this->markEmailVerified($user);

        $this->persistence->persist($user);
        $this->identityWriteRepository->save($this->newIdentity($user, $providerUserId, $email));
        $this->persistence->flush();

        return new GoogleOidcAuthenticateResult($user, GoogleOidcAuthenticationAction::USER_CREATED);
    }

    private function newIdentity(UserEntity $user, string $providerUserId, string $email): UserIdentityEntity
    {
        return new UserIdentityEntity()
            ->setUser($user)
            ->setProvider(self::PROVIDER)
            ->setProviderUserId($providerUserId)
            ->setProviderEmail($email);
    }

    private function markEmailVerified(UserEntity $user): void
    {
        $user
            ->setIsEmailVerified(true)
            ->setEmailVerificationTokenHash(null)
            ->setEmailVerificationExpiresAt(null)
            ->setEmailVerificationRequestedAt(null);
    }
}
