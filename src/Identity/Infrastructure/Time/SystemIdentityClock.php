<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Time;

use App\Identity\Application\Time\IdentityClockInterface;

final class SystemIdentityClock implements IdentityClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
