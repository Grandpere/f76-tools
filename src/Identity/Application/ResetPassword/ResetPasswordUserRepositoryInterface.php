<?php

declare(strict_types=1);

namespace App\Identity\Application\ResetPassword;

use App\Entity\UserEntity;

interface ResetPasswordUserRepositoryInterface
{
    public function findOneByResetPasswordTokenHash(string $tokenHash): ?UserEntity;
}
