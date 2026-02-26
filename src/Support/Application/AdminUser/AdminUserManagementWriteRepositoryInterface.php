<?php

declare(strict_types=1);

namespace App\Support\Application\AdminUser;

use App\Entity\UserEntity;

interface AdminUserManagementWriteRepositoryInterface
{
    public function getById(int $id): ?UserEntity;

    public function save(UserEntity $user): void;
}
