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

namespace App\Support\Application\AdminUser;

use App\Identity\Domain\Entity\UserEntity;

final class ResendVerificationEmailResult
{
    private function __construct(
        private readonly ResendVerificationEmailStatus $status,
        private readonly ?UserEntity $targetUser,
        private readonly ?string $plainToken,
        private readonly int $remainingSeconds,
    ) {
    }

    public static function userNotFound(): self
    {
        return new self(ResendVerificationEmailStatus::USER_NOT_FOUND, null, null, 0);
    }

    public static function alreadyVerified(UserEntity $targetUser): self
    {
        return new self(ResendVerificationEmailStatus::ALREADY_VERIFIED, $targetUser, null, 0);
    }

    public static function rateLimited(UserEntity $targetUser, int $remainingSeconds): self
    {
        return new self(ResendVerificationEmailStatus::RATE_LIMITED, $targetUser, null, $remainingSeconds);
    }

    public static function generated(UserEntity $targetUser, string $plainToken): self
    {
        return new self(ResendVerificationEmailStatus::GENERATED, $targetUser, $plainToken, 0);
    }

    public function status(): ResendVerificationEmailStatus
    {
        return $this->status;
    }

    public function targetUser(): ?UserEntity
    {
        return $this->targetUser;
    }

    public function plainToken(): ?string
    {
        return $this->plainToken;
    }

    public function remainingSeconds(): int
    {
        return $this->remainingSeconds;
    }
}
