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

final class ResendVerificationRequestApplicationService
{
    public function __construct(
        private readonly ResendVerificationUserRepositoryInterface $userRepository,
        private readonly IdentityWritePersistenceInterface $persistence,
        private readonly TemporaryLinkPolicy $temporaryLinkPolicy,
    ) {
    }

    public function request(ResendVerificationRequest $request): ResendVerificationRequestResult
    {
        if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            return ResendVerificationRequestResult::noAction();
        }

        $user = $this->userRepository->findOneByEmail($request->email);
        if (null === $user || $user->isEmailVerified()) {
            return ResendVerificationRequestResult::noAction();
        }

        $remaining = $this->temporaryLinkPolicy->cooldownRemainingSeconds(
            $user->getEmailVerificationRequestedAt(),
            $request->requestedAt,
            $this->temporaryLinkPolicy->getEmailVerificationResendCooldownSeconds(),
        );
        if ($remaining > 0) {
            return ResendVerificationRequestResult::noAction();
        }

        $token = bin2hex(random_bytes(32));
        $user->setEmailVerificationTokenHash(hash('sha256', $token));
        $user->setEmailVerificationExpiresAt($this->temporaryLinkPolicy->expiresAt($request->requestedAt, $this->temporaryLinkPolicy->getEmailVerificationTtl()));
        $user->setEmailVerificationRequestedAt($request->requestedAt);
        $this->persistence->flush();

        return ResendVerificationRequestResult::tokenIssued($user->getEmail(), $token);
    }
}
