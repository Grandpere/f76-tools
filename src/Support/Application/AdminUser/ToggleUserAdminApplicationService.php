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

use App\Identity\Domain\Entity\UserEntity;

final class ToggleUserAdminApplicationService
{
    public function __construct(
        private readonly AdminUserManagementWriteRepository $userRepository,
    ) {
    }

    public function toggle(int $targetUserId, UserEntity $actor): ToggleUserAdminResult
    {
        $target = $this->userRepository->getById($targetUserId);
        if (!$target instanceof UserEntity) {
            return ToggleUserAdminResult::USER_NOT_FOUND;
        }

        if ($actor->getId() === $target->getId()) {
            return ToggleUserAdminResult::CANNOT_CHANGE_SELF;
        }

        $roles = $target->getRoles();
        $isAdmin = in_array('ROLE_ADMIN', $roles, true);
        if ($isAdmin) {
            $target->setRoles(array_values(array_filter($roles, static fn (string $role): bool => 'ROLE_ADMIN' !== $role)));
        } else {
            $roles[] = 'ROLE_ADMIN';
            $target->setRoles(array_values(array_unique($roles)));
        }

        $this->userRepository->save($target);

        return ToggleUserAdminResult::UPDATED;
    }
}
