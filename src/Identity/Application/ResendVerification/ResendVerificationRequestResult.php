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

final class ResendVerificationRequestResult
{
    private function __construct(
        private readonly bool $tokenIssued,
        private readonly ?string $email,
        private readonly ?string $plainToken,
    ) {
    }

    public static function noAction(): self
    {
        return new self(false, null, null);
    }

    public static function tokenIssued(string $email, string $plainToken): self
    {
        return new self(true, $email, $plainToken);
    }

    public function isTokenIssued(): bool
    {
        return $this->tokenIssued;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPlainToken(): ?string
    {
        return $this->plainToken;
    }
}
