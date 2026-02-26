<?php

declare(strict_types=1);

namespace App\Identity\Application\VerifyEmail;

interface IdentityWritePersistenceInterface
{
    public function flush(): void;
}
