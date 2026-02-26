<?php

declare(strict_types=1);

namespace App\Identity\Application\Registration;

use App\Entity\UserEntity;
use App\Identity\Application\Common\IdentityPasswordHasherInterface;
use App\Identity\Application\Common\IdentityWritePersistenceInterface;
use App\Security\TemporaryLinkPolicy;
use DateTimeImmutable;

final class RegisterUserApplicationService
{
    public function __construct(
        private readonly RegistrationUserRepositoryInterface $userRepository,
        private readonly IdentityPasswordHasherInterface $passwordHasher,
        private readonly IdentityWritePersistenceInterface $persistence,
        private readonly TemporaryLinkPolicy $temporaryLinkPolicy,
    ) {
    }

    public function register(
        string $email,
        string $password,
        string $passwordConfirm,
        DateTimeImmutable $now,
    ): RegisterUserResult {
        $normalizedEmail = mb_strtolower(trim($email));
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            return RegisterUserResult::ofStatus(RegisterUserStatus::INVALID_EMAIL);
        }

        if (strlen($password) < 8) {
            return RegisterUserResult::ofStatus(RegisterUserStatus::PASSWORD_TOO_SHORT);
        }

        if ($password !== $passwordConfirm) {
            return RegisterUserResult::ofStatus(RegisterUserStatus::PASSWORD_MISMATCH);
        }

        if ($this->userRepository->findOneByEmail($normalizedEmail) instanceof UserEntity) {
            return RegisterUserResult::ofStatus(RegisterUserStatus::EMAIL_EXISTS);
        }

        $user = (new UserEntity())
            ->setEmail($normalizedEmail)
            ->setRoles(['ROLE_USER'])
            ->setIsEmailVerified(false);
        $user->setPassword($this->passwordHasher->hash($user, $password));

        $token = bin2hex(random_bytes(32));
        $user->setEmailVerificationTokenHash(hash('sha256', $token));
        $user->setEmailVerificationExpiresAt($this->temporaryLinkPolicy->expiresAt($now, $this->temporaryLinkPolicy->getEmailVerificationTtl()));
        $user->setEmailVerificationRequestedAt($now);

        $this->persistence->persist($user);
        $this->persistence->flush();

        return RegisterUserResult::success($normalizedEmail, $token);
    }
}
