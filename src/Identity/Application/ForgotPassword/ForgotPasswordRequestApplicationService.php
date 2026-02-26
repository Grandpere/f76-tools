<?php

declare(strict_types=1);

namespace App\Identity\Application\ForgotPassword;

use App\Identity\Application\Common\IdentityWritePersistenceInterface;
use App\Security\TemporaryLinkPolicy;
use DateTimeImmutable;

final class ForgotPasswordRequestApplicationService
{
    public function __construct(
        private readonly ForgotPasswordUserRepositoryInterface $userRepository,
        private readonly IdentityWritePersistenceInterface $persistence,
        private readonly TemporaryLinkPolicy $temporaryLinkPolicy,
    ) {
    }

    public function request(string $email, DateTimeImmutable $now): ForgotPasswordRequestResult
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ForgotPasswordRequestResult::noAction();
        }

        $user = $this->userRepository->findOneByEmail($email);
        if (null === $user) {
            return ForgotPasswordRequestResult::noAction();
        }

        $remaining = $this->temporaryLinkPolicy->cooldownRemainingSeconds(
            $user->getResetPasswordRequestedAt(),
            $now,
            $this->temporaryLinkPolicy->getResetLinkCooldownSeconds(),
        );
        if ($remaining > 0) {
            return ForgotPasswordRequestResult::noAction();
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetPasswordTokenHash(hash('sha256', $token));
        $user->setResetPasswordExpiresAt($this->temporaryLinkPolicy->expiresAt($now, $this->temporaryLinkPolicy->getResetPasswordTtl()));
        $user->setResetPasswordRequestedAt($now);
        $this->persistence->flush();

        return ForgotPasswordRequestResult::tokenIssued($user->getEmail(), $token);
    }
}
