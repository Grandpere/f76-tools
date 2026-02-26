<?php

declare(strict_types=1);

namespace App\Identity\Application\ResendVerification;

use App\Entity\UserEntity;

interface ResendVerificationUserRepositoryInterface
{
    public function findOneByEmail(string $email): ?UserEntity;
}
