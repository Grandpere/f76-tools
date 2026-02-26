<?php

declare(strict_types=1);

namespace App\Identity\Application\Registration;

final class RegisterUserResult
{
    private function __construct(
        private readonly RegisterUserStatus $status,
        private readonly ?string $email,
        private readonly ?string $plainVerificationToken,
    ) {
    }

    public static function ofStatus(RegisterUserStatus $status): self
    {
        return new self($status, null, null);
    }

    public static function success(string $email, string $plainVerificationToken): self
    {
        return new self(RegisterUserStatus::SUCCESS, $email, $plainVerificationToken);
    }

    public function getStatus(): RegisterUserStatus
    {
        return $this->status;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPlainVerificationToken(): ?string
    {
        return $this->plainVerificationToken;
    }
}
