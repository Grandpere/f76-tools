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

namespace App\Support\Application\AdminUser;

use App\Identity\Application\Security\TemporaryLinkPolicy;
use App\Identity\Application\Time\IdentityClockInterface;

final class ResendVerificationEmailApplicationService
{
    public function __construct(
        private readonly AdminUserManagementWriteRepository $userRepository,
        private readonly TemporaryLinkPolicy $temporaryLinkPolicy,
        private readonly IdentityClockInterface $identityClock,
    ) {
    }

    public function request(int $targetUserId): ResendVerificationEmailResult
    {
        $targetUser = $this->userRepository->getById($targetUserId);
        if (null === $targetUser) {
            return ResendVerificationEmailResult::userNotFound();
        }

        if ($targetUser->isEmailVerified()) {
            return ResendVerificationEmailResult::alreadyVerified($targetUser);
        }

        $now = $this->identityClock->now();
        $remaining = $this->temporaryLinkPolicy->cooldownRemainingSeconds(
            $targetUser->getEmailVerificationRequestedAt(),
            $now,
            $this->temporaryLinkPolicy->getEmailVerificationResendCooldownSeconds(),
        );
        if ($remaining > 0) {
            return ResendVerificationEmailResult::rateLimited($targetUser, $remaining);
        }

        $token = bin2hex(random_bytes(32));
        $targetUser->setEmailVerificationTokenHash(hash('sha256', $token));
        $targetUser->setEmailVerificationExpiresAt($this->temporaryLinkPolicy->expiresAt($now, $this->temporaryLinkPolicy->getEmailVerificationTtl()));
        $targetUser->setEmailVerificationRequestedAt($now);
        $this->userRepository->save($targetUser);

        return ResendVerificationEmailResult::generated($targetUser, $token);
    }
}
