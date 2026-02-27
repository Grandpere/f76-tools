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

use App\Entity\UserEntity;

final class ToggleUserActiveApplicationService
{
    public function __construct(
        private readonly AdminUserManagementWriteRepositoryInterface $userRepository,
    ) {
    }

    public function toggle(int $targetUserId, UserEntity $actor): ToggleUserActiveResult
    {
        $target = $this->userRepository->getById($targetUserId);
        if (!$target instanceof UserEntity) {
            return ToggleUserActiveResult::USER_NOT_FOUND;
        }

        if ($actor->getId() === $target->getId()) {
            return ToggleUserActiveResult::CANNOT_CHANGE_SELF;
        }

        $target->setIsActive(!$target->isActive());
        $this->userRepository->save($target);

        return ToggleUserActiveResult::UPDATED;
    }
}
