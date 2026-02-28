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

final class ForceVerifyEmailApplicationService
{
    public function __construct(
        private readonly AdminUserManagementWriteRepositoryInterface $userRepository,
    ) {
    }

    public function verify(int $targetUserId): ForceVerifyEmailResult
    {
        $targetUser = $this->userRepository->getById($targetUserId);
        if (null === $targetUser) {
            return ForceVerifyEmailResult::USER_NOT_FOUND;
        }

        if ($targetUser->isEmailVerified()) {
            return ForceVerifyEmailResult::ALREADY_VERIFIED;
        }

        $targetUser->setIsEmailVerified(true);
        $targetUser->setEmailVerificationTokenHash(null);
        $targetUser->setEmailVerificationExpiresAt(null);
        $targetUser->setEmailVerificationRequestedAt(null);
        $this->userRepository->save($targetUser);

        return ForceVerifyEmailResult::VERIFIED;
    }
}
