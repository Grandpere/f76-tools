<?php

declare(strict_types=1);

namespace App\Identity\Application\Registration;

use App\Entity\UserEntity;

interface RegistrationUserRepositoryInterface
{
    public function findOneByEmail(string $email): ?UserEntity;
}
