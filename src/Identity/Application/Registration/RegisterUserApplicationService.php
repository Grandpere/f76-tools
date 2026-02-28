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

namespace App\Identity\Application\Registration;

use App\Identity\Application\Common\IdentityPasswordHasher;
use App\Identity\Application\Common\IdentityWritePersistence;
use App\Identity\Application\Security\TemporaryLinkPolicy;
use App\Identity\Domain\Entity\UserEntity;

final class RegisterUserApplicationService
{
    public function __construct(
        private readonly RegistrationUserRepository $userRepository,
        private readonly IdentityPasswordHasher $passwordHasher,
        private readonly IdentityWritePersistence $persistence,
        private readonly TemporaryLinkPolicy $temporaryLinkPolicy,
    ) {
    }

    public function register(RegisterUserRequest $request): RegisterUserResult
    {
        $normalizedEmail = $request->email;
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            return RegisterUserResult::ofStatus(RegisterUserStatus::INVALID_EMAIL);
        }

        if (strlen($request->password) < 8) {
            return RegisterUserResult::ofStatus(RegisterUserStatus::PASSWORD_TOO_SHORT);
        }

        if ($request->password !== $request->passwordConfirm) {
            return RegisterUserResult::ofStatus(RegisterUserStatus::PASSWORD_MISMATCH);
        }

        if ($this->userRepository->findOneByEmail($normalizedEmail) instanceof UserEntity) {
            return RegisterUserResult::ofStatus(RegisterUserStatus::EMAIL_EXISTS);
        }

        $user = new UserEntity()
            ->setEmail($normalizedEmail)
            ->setRoles(['ROLE_USER'])
            ->setIsEmailVerified(false)
            ->setHasLocalPassword(true);
        $user->setPassword($this->passwordHasher->hash($user, $request->password));

        $token = bin2hex(random_bytes(32));
        $user->setEmailVerificationTokenHash(hash('sha256', $token));
        $user->setEmailVerificationExpiresAt($this->temporaryLinkPolicy->expiresAt($request->requestedAt, $this->temporaryLinkPolicy->getEmailVerificationTtl()));
        $user->setEmailVerificationRequestedAt($request->requestedAt);

        $this->persistence->persist($user);
        $this->persistence->flush();

        return RegisterUserResult::success($normalizedEmail, $token);
    }
}
