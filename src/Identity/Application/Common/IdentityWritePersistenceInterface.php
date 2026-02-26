<?php

declare(strict_types=1);

namespace App\Identity\Application\Common;

use App\Entity\UserEntity;

interface IdentityWritePersistenceInterface
{
    public function persist(UserEntity $user): void;

    public function flush(): void;
}
