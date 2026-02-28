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

namespace App\Tests\Unit\Support\Application\AdminUser;

use App\Identity\Application\Security\TemporaryLinkPolicy;
use App\Identity\Application\Time\IdentityClockInterface;
use App\Identity\Domain\Entity\UserEntity;
use App\Support\Application\AdminUser\AdminUserManagementWriteRepositoryInterface;
use App\Support\Application\AdminUser\ResendVerificationEmailApplicationService;
use App\Support\Application\AdminUser\ResendVerificationEmailStatus;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResendVerificationEmailApplicationServiceTest extends TestCase
{
    private AdminUserManagementWriteRepositoryInterface&MockObject $userRepository;
    private IdentityClockInterface&MockObject $clock;
    private TemporaryLinkPolicy $temporaryLinkPolicy;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $this->clock = $this->createMock(IdentityClockInterface::class);
        $this->temporaryLinkPolicy = new TemporaryLinkPolicy();
    }

    public function testReturnsUserNotFoundWhenTargetDoesNotExist(): void
    {
        $this->userRepository->expects(self::once())->method('getById')->with(99)->willReturn(null);
        $this->userRepository->expects(self::never())->method('save');

        $result = $this->service()->request(99);

        self::assertSame(ResendVerificationEmailStatus::USER_NOT_FOUND, $result->status());
    }

    public function testReturnsAlreadyVerifiedWhenUserIsVerified(): void
    {
        $user = new UserEntity()
            ->setEmail('verified@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(true);

        $this->userRepository->expects(self::once())->method('getById')->with(10)->willReturn($user);
        $this->userRepository->expects(self::never())->method('save');

        $result = $this->service()->request(10);

        self::assertSame(ResendVerificationEmailStatus::ALREADY_VERIFIED, $result->status());
        self::assertSame($user, $result->targetUser());
    }

    public function testReturnsRateLimitedWhenCooldownIsActive(): void
    {
        $user = new UserEntity()
            ->setEmail('pending@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(false)
            ->setEmailVerificationRequestedAt(new DateTimeImmutable('2026-02-28 10:00:00'));

        $this->userRepository->expects(self::once())->method('getById')->with(10)->willReturn($user);
        $this->clock->method('now')->willReturn(new DateTimeImmutable('2026-02-28 10:00:10'));
        $this->userRepository->expects(self::never())->method('save');

        $result = $this->service()->request(10);

        self::assertSame(ResendVerificationEmailStatus::RATE_LIMITED, $result->status());
        self::assertGreaterThan(0, $result->remainingSeconds());
    }

    public function testGeneratesTokenForUnverifiedUserWhenCooldownExpired(): void
    {
        $user = new UserEntity()
            ->setEmail('pending@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(false)
            ->setEmailVerificationRequestedAt(new DateTimeImmutable('2026-02-28 08:00:00'));

        $this->userRepository->expects(self::once())->method('getById')->with(10)->willReturn($user);
        $this->clock->method('now')->willReturn(new DateTimeImmutable('2026-02-28 10:00:00'));
        $this->userRepository->expects(self::once())->method('save')->with($user);

        $result = $this->service()->request(10);

        self::assertSame(ResendVerificationEmailStatus::GENERATED, $result->status());
        self::assertNotNull($result->plainToken());
        self::assertNotNull($user->getEmailVerificationTokenHash());
        self::assertNotNull($user->getEmailVerificationExpiresAt());
        self::assertNotNull($user->getEmailVerificationRequestedAt());
    }

    private function service(): ResendVerificationEmailApplicationService
    {
        return new ResendVerificationEmailApplicationService(
            $this->userRepository,
            $this->temporaryLinkPolicy,
            $this->clock,
        );
    }
}
