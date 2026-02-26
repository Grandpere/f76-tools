<?php

declare(strict_types=1);

namespace App\Identity\Application\VerifyEmail;

use App\Entity\UserEntity;

interface VerifyEmailUserRepositoryInterface
{
    public function findOneByEmailVerificationTokenHash(string $tokenHash): ?UserEntity;
}
