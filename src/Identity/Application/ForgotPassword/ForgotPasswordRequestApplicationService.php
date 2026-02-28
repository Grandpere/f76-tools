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

namespace App\Identity\Application\ForgotPassword;

use App\Identity\Application\Common\IdentityWritePersistence;
use App\Identity\Application\Security\TemporaryLinkPolicy;

final class ForgotPasswordRequestApplicationService
{
    public function __construct(
        private readonly ForgotPasswordUserRepository $userRepository,
        private readonly IdentityWritePersistence $persistence,
        private readonly TemporaryLinkPolicy $temporaryLinkPolicy,
    ) {
    }

    public function request(ForgotPasswordRequest $request): ForgotPasswordRequestResult
    {
        if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            return ForgotPasswordRequestResult::noAction();
        }

        $user = $this->userRepository->findOneByEmail($request->email);
        if (null === $user) {
            return ForgotPasswordRequestResult::noAction();
        }

        $remaining = $this->temporaryLinkPolicy->cooldownRemainingSeconds(
            $user->getResetPasswordRequestedAt(),
            $request->requestedAt,
            $this->temporaryLinkPolicy->getResetLinkCooldownSeconds(),
        );
        if ($remaining > 0) {
            return ForgotPasswordRequestResult::noAction();
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetPasswordTokenHash(hash('sha256', $token));
        $user->setResetPasswordExpiresAt($this->temporaryLinkPolicy->expiresAt($request->requestedAt, $this->temporaryLinkPolicy->getResetPasswordTtl()));
        $user->setResetPasswordRequestedAt($request->requestedAt);
        $this->persistence->flush();

        return ForgotPasswordRequestResult::tokenIssued($user->getEmail(), $token);
    }
}
