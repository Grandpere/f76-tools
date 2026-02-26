<?php

declare(strict_types=1);

namespace App\Identity\Application\Common;

interface IdentityWritePersistenceInterface
{
    public function flush(): void;
}
