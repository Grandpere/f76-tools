<?php

declare(strict_types=1);

namespace App\Identity\Application\ForgotPassword;

use App\Entity\UserEntity;

interface ForgotPasswordUserRepositoryInterface
{
    public function findOneByEmail(string $email): ?UserEntity;
}
