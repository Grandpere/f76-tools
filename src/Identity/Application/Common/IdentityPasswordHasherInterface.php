<?php

declare(strict_types=1);

namespace App\Identity\Application\Common;

use App\Entity\UserEntity;

interface IdentityPasswordHasherInterface
{
    public function hash(UserEntity $user, string $plainPassword): string;
}
