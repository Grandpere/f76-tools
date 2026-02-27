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

namespace App\Identity\Application\VerifyEmail;

use App\Identity\Application\Common\IdentityWritePersistenceInterface;
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
