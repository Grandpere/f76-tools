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

namespace App\Identity\Application\ChangePassword;

use App\Identity\Application\Common\IdentityPasswordHasher;
use App\Identity\Application\Common\IdentityPasswordVerifier;
use App\Identity\Application\Common\IdentityWritePersistence;
use App\Identity\Domain\Entity\UserEntity;

final class ChangePasswordApplicationService
{
    public function __construct(
        private readonly IdentityPasswordVerifier $passwordVerifier,
        private readonly IdentityPasswordHasher $passwordHasher,
        private readonly IdentityWritePersistence $persistence,
    ) {
    }

    public function change(UserEntity $user, ChangePasswordRequest $request): ChangePasswordResult
    {
        if (!$this->passwordVerifier->isValid($user, $request->currentPassword)) {
            return ChangePasswordResult::CURRENT_PASSWORD_INVALID;
        }

        if (strlen($request->newPassword) < 8) {
            return ChangePasswordResult::PASSWORD_TOO_SHORT;
        }

        if ($request->newPassword !== $request->newPasswordConfirm) {
            return ChangePasswordResult::PASSWORD_MISMATCH;
        }

        $user->setPassword($this->passwordHasher->hash($user, $request->newPassword));
        $user->setHasLocalPassword(true);
        $user->setResetPasswordTokenHash(null);
        $user->setResetPasswordExpiresAt(null);
        $user->setResetPasswordRequestedAt(null);
        $this->persistence->flush();

        return ChangePasswordResult::SUCCESS;
    }
}
