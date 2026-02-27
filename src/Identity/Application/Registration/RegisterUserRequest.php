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

namespace App\Identity\Application\Registration;

use DateTimeImmutable;

final readonly class RegisterUserRequest
{
    private function __construct(
        public string $email,
        public string $password,
        public string $passwordConfirm,
        public DateTimeImmutable $requestedAt,
    ) {
    }

    public static function fromRaw(
        string $email,
        string $password,
        string $passwordConfirm,
        DateTimeImmutable $requestedAt,
    ): self {
        return new self(
            mb_strtolower(trim($email)),
            $password,
            $passwordConfirm,
            $requestedAt,
        );
    }
}
