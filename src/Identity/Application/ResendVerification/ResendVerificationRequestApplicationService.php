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

use App\Identity\Application\Common\IdentityWritePersistenceInterface;
use App\Identity\Application\Security\TemporaryLinkPolicy;
use DateTimeImmutable;

final class ResendVerificationRequestApplicationService
{
    public function __construct(
        private readonly ResendVerificationUserRepositoryInterface $userRepository,
        private readonly IdentityWritePersistenceInterface $persistence,
        private readonly TemporaryLinkPolicy $temporaryLinkPolicy,
    ) {
    }

    public function request(string $email, DateTimeImmutable $now): ResendVerificationRequestResult
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ResendVerificationRequestResult::noAction();
        }

        $user = $this->userRepository->findOneByEmail($email);
        if (null === $user || $user->isEmailVerified()) {
            return ResendVerificationRequestResult::noAction();
        }

        $remaining = $this->temporaryLinkPolicy->cooldownRemainingSeconds(
            $user->getEmailVerificationRequestedAt(),
            $now,
            $this->temporaryLinkPolicy->getEmailVerificationResendCooldownSeconds(),
        );
        if ($remaining > 0) {
            return ResendVerificationRequestResult::noAction();
        }

        $token = bin2hex(random_bytes(32));
        $user->setEmailVerificationTokenHash(hash('sha256', $token));
        $user->setEmailVerificationExpiresAt($this->temporaryLinkPolicy->expiresAt($now, $this->temporaryLinkPolicy->getEmailVerificationTtl()));
        $user->setEmailVerificationRequestedAt($now);
        $this->persistence->flush();

        return ResendVerificationRequestResult::tokenIssued($user->getEmail(), $token);
    }
}
