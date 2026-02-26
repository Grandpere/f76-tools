<?php

declare(strict_types=1);

namespace App\Identity\Application\ResetPassword;

use App\Entity\UserEntity;
use App\Identity\Application\Common\IdentityPasswordHasherInterface;
use App\Identity\Application\Common\IdentityWritePersistenceInterface;
use DateTimeImmutable;

final class ResetPasswordApplicationService
{
    public function __construct(
        private readonly ResetPasswordUserRepositoryInterface $userRepository,
        private readonly IdentityPasswordHasherInterface $passwordHasher,
        private readonly IdentityWritePersistenceInterface $persistence,
    ) {
    }

    public function canResetToken(string $token, DateTimeImmutable $now): bool
    {
        return null !== $this->resolveValidUserByToken($token, $now);
    }

    public function resetByPlainToken(
        string $token,
        string $password,
        string $passwordConfirm,
        DateTimeImmutable $now,
    ): ResetPasswordResult {
        $user = $this->resolveValidUserByToken($token, $now);
        if (null === $user) {
            return ResetPasswordResult::INVALID_OR_EXPIRED;
        }

        if (strlen($password) < 8) {
            return ResetPasswordResult::PASSWORD_TOO_SHORT;
        }

        if ($password !== $passwordConfirm) {
            return ResetPasswordResult::PASSWORD_MISMATCH;
        }

        $user->setPassword($this->passwordHasher->hash($user, $password));
        $user->setResetPasswordTokenHash(null);
        $user->setResetPasswordExpiresAt(null);
        $user->setResetPasswordRequestedAt(null);
        $this->persistence->flush();

        return ResetPasswordResult::SUCCESS;
    }

    private function resolveValidUserByToken(string $token, DateTimeImmutable $now): ?UserEntity
    {
        if ('' === trim($token)) {
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->userRepository->findOneByResetPasswordTokenHash($tokenHash);
        if (null === $user) {
            return null;
        }

        $expiresAt = $user->getResetPasswordExpiresAt();
        if (null === $expiresAt || $expiresAt < $now) {
            return null;
        }

        return $user;
    }
}
