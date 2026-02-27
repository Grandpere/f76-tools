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

use App\Entity\UserEntity;
use App\Identity\Application\Security\TemporaryLinkPolicy;
use DateInterval;
use DateTimeImmutable;

final class GenerateResetLinkApplicationService
{
    public function __construct(
        private readonly AdminUserManagementWriteRepositoryInterface $userRepository,
        private readonly AdminUserAuditReadRepositoryInterface $auditRepository,
        private readonly TemporaryLinkPolicy $temporaryLinkPolicy,
    ) {
    }

    public function generate(int $targetUserId, UserEntity $actor): GenerateResetLinkResult
    {
        $target = $this->userRepository->getById($targetUserId);
        if (!$target instanceof UserEntity) {
            return GenerateResetLinkResult::userNotFound();
        }

        $now = new DateTimeImmutable();
        $globalWindowSeconds = $this->temporaryLinkPolicy->getResetLinkGlobalWindowSeconds();
        $globalMaxRequests = $this->temporaryLinkPolicy->getResetLinkGlobalMaxRequests();
        $globalWindowStart = $now->sub(new DateInterval(sprintf('PT%dS', $globalWindowSeconds)));

        $recentGenerations = $this->auditRepository->countRecentActionsByActor($actor, ['user_generate_reset_link'], $globalWindowStart);
        if ($recentGenerations >= $globalMaxRequests) {
            return GenerateResetLinkResult::globalRateLimited($target, $globalWindowSeconds, $globalMaxRequests);
        }

        $remaining = $this->temporaryLinkPolicy->cooldownRemainingSeconds(
            $target->getResetPasswordRequestedAt(),
            $now,
            $this->temporaryLinkPolicy->getResetLinkCooldownSeconds(),
        );
        if ($remaining > 0) {
            return GenerateResetLinkResult::cooldownRateLimited($target, $remaining);
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = $this->temporaryLinkPolicy->expiresAt($now, $this->temporaryLinkPolicy->getResetPasswordTtl());

        $target->setResetPasswordTokenHash($tokenHash);
        $target->setResetPasswordExpiresAt($expiresAt);
        $target->setResetPasswordRequestedAt($now);
        $this->userRepository->save($target);

        return GenerateResetLinkResult::generated($target, $token, $expiresAt);
    }
}
