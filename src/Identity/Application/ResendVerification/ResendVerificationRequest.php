<?php

declare(strict_types=1);

/*
 * This file is part of a F76 project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Identity\Application\ResendVerification;

use DateTimeImmutable;

final readonly class ResendVerificationRequest
{
    private function __construct(
        public string $email,
        public DateTimeImmutable $requestedAt,
    ) {
    }

    public static function fromRaw(string $email, DateTimeImmutable $requestedAt): self
    {
        return new self(
            mb_strtolower(trim($email)),
            $requestedAt,
        );
    }
}
