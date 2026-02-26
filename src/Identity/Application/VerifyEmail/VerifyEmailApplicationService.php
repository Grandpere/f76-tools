<?php

declare(strict_types=1);

namespace App\Identity\Application\VerifyEmail;

use DateTimeImmutable;

final class VerifyEmailApplicationService
{
    public function __construct(
        private readonly VerifyEmailUserRepositoryInterface $userRepository,
        private readonly IdentityWritePersistenceInterface $persistence,
    ) {
    }

    public function verifyByPlainToken(string $token, DateTimeImmutable $now): bool
    {
        if ('' === trim($token)) {
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->userRepository->findOneByEmailVerificationTokenHash($tokenHash);
        if (null === $user) {
            return false;
        }

        $expiresAt = $user->getEmailVerificationExpiresAt();
        if (null === $expiresAt || $expiresAt < $now) {
            return false;
        }

        $user->setIsEmailVerified(true);
        $user->setEmailVerificationTokenHash(null);
        $user->setEmailVerificationExpiresAt(null);
        $user->setEmailVerificationRequestedAt(null);
        $this->persistence->flush();

        return true;
    }
}
