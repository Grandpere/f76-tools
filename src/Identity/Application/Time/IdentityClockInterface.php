<?php

declare(strict_types=1);

namespace App\Identity\Application\Time;

interface IdentityClockInterface
{
    public function now(): \DateTimeImmutable;
}
