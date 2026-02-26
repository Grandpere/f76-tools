<?php

declare(strict_types=1);

namespace App\Support\Application\AdminUser;

use App\Entity\UserEntity;
use DateTimeImmutable;

final class GenerateResetLinkResult
{
    private function __construct(
        private readonly GenerateResetLinkStatus $status,
        private readonly ?UserEntity $targetUser = null,
        private readonly ?string $token = null,
        private readonly ?DateTimeImmutable $expiresAt = null,
        private readonly int $remainingSeconds = 0,
        private readonly int $windowSeconds = 0,
        private readonly int $maxRequests = 0,
    ) {
    }

    public static function actorRequired(): self
    {
        return new self(GenerateResetLinkStatus::ACTOR_REQUIRED);
    }

    public static function userNotFound(): self
    {
        return new self(GenerateResetLinkStatus::USER_NOT_FOUND);
    }

    public static function globalRateLimited(UserEntity $targetUser, int $windowSeconds, int $maxRequests): self
    {
        return new self(
            GenerateResetLinkStatus::GLOBAL_RATE_LIMITED,
            targetUser: $targetUser,
            windowSeconds: $windowSeconds,
            maxRequests: $maxRequests,
        );
    }

    public static function cooldownRateLimited(UserEntity $targetUser, int $remainingSeconds): self
    {
        return new self(
            GenerateResetLinkStatus::COOLDOWN_RATE_LIMITED,
            targetUser: $targetUser,
            remainingSeconds: $remainingSeconds,
        );
    }

    public static function generated(UserEntity $targetUser, string $token, DateTimeImmutable $expiresAt): self
    {
        return new self(
            GenerateResetLinkStatus::GENERATED,
            targetUser: $targetUser,
            token: $token,
            expiresAt: $expiresAt,
        );
    }

    public function getStatus(): GenerateResetLinkStatus
    {
        return $this->status;
    }

    public function getTargetUser(): ?UserEntity
    {
        return $this->targetUser;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRemainingSeconds(): int
    {
        return $this->remainingSeconds;
    }

    public function getWindowSeconds(): int
    {
        return $this->windowSeconds;
    }

    public function getMaxRequests(): int
    {
        return $this->maxRequests;
    }
}

