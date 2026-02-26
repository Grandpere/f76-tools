<?php

declare(strict_types=1);

namespace App\Support\Application\AdminUser;

use App\Entity\UserEntity;

final class ToggleUserActiveApplicationService
{
    public function __construct(
        private readonly AdminUserManagementWriteRepositoryInterface $userRepository,
    ) {
    }

    public function toggle(int $targetUserId, mixed $actor): ToggleUserActiveResult
    {
        if (!$actor instanceof UserEntity) {
            return ToggleUserActiveResult::ACTOR_REQUIRED;
        }

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
