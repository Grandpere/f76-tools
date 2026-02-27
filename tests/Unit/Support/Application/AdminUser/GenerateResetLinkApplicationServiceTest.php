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

use App\Entity\UserEntity;
use App\Security\TemporaryLinkPolicy;
use App\Support\Application\AdminUser\AdminUserAuditReadRepositoryInterface;
use App\Support\Application\AdminUser\AdminUserManagementWriteRepositoryInterface;
use App\Support\Application\AdminUser\GenerateResetLinkApplicationService;
use App\Support\Application\AdminUser\GenerateResetLinkStatus;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GenerateResetLinkApplicationServiceTest extends TestCase
{
    public function testGenerateReturnsUserNotFoundWhenTargetDoesNotExist(): void
    {
        $actor = $this->createUser('admin@example.com', ['ROLE_ADMIN']);

        /** @var AdminUserManagementWriteRepositoryInterface&MockObject $userRepository */
        $userRepository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $userRepository->expects(self::once())->method('getById')->with(10)->willReturn(null);
        $userRepository->expects(self::never())->method('save');

        /** @var AdminUserAuditReadRepositoryInterface&MockObject $auditRepository */
        $auditRepository = $this->createMock(AdminUserAuditReadRepositoryInterface::class);
        $auditRepository->expects(self::never())->method('countRecentActionsByActor');

        $service = new GenerateResetLinkApplicationService($userRepository, $auditRepository, new TemporaryLinkPolicy());
        $result = $service->generate(10, $actor);

        self::assertSame(GenerateResetLinkStatus::USER_NOT_FOUND, $result->getStatus());
    }

    public function testGenerateReturnsGlobalRateLimitedWhenActorExceededWindow(): void
    {
        $actor = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $target = $this->createUser('managed@example.com', ['ROLE_USER']);

        /** @var AdminUserManagementWriteRepositoryInterface&MockObject $userRepository */
        $userRepository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $userRepository->expects(self::once())->method('getById')->with(10)->willReturn($target);
        $userRepository->expects(self::never())->method('save');

        /** @var AdminUserAuditReadRepositoryInterface&MockObject $auditRepository */
        $auditRepository = $this->createMock(AdminUserAuditReadRepositoryInterface::class);
        $auditRepository->expects(self::once())->method('countRecentActionsByActor')->willReturn(10);

        $service = new GenerateResetLinkApplicationService($userRepository, $auditRepository, new TemporaryLinkPolicy());
        $result = $service->generate(10, $actor);

        self::assertSame(GenerateResetLinkStatus::GLOBAL_RATE_LIMITED, $result->getStatus());
        self::assertSame(60, $result->getWindowSeconds());
        self::assertSame(10, $result->getMaxRequests());
        self::assertSame($target, $result->getTargetUser());
    }

    public function testGenerateReturnsCooldownRateLimitedWhenTargetHasRecentRequest(): void
    {
        $actor = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $target = $this->createUser('managed@example.com', ['ROLE_USER'])
            ->setResetPasswordRequestedAt(new DateTimeImmutable());

        /** @var AdminUserManagementWriteRepositoryInterface&MockObject $userRepository */
        $userRepository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $userRepository->expects(self::once())->method('getById')->with(10)->willReturn($target);
        $userRepository->expects(self::never())->method('save');

        /** @var AdminUserAuditReadRepositoryInterface&MockObject $auditRepository */
        $auditRepository = $this->createMock(AdminUserAuditReadRepositoryInterface::class);
        $auditRepository->expects(self::once())->method('countRecentActionsByActor')->willReturn(0);

        $service = new GenerateResetLinkApplicationService($userRepository, $auditRepository, new TemporaryLinkPolicy());
        $result = $service->generate(10, $actor);

        self::assertSame(GenerateResetLinkStatus::COOLDOWN_RATE_LIMITED, $result->getStatus());
        self::assertGreaterThan(0, $result->getRemainingSeconds());
        self::assertSame($target, $result->getTargetUser());
    }

    public function testGeneratePersistsResetTokenWhenRequestIsAllowed(): void
    {
        $actor = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $target = $this->createUser('managed@example.com', ['ROLE_USER']);

        /** @var AdminUserManagementWriteRepositoryInterface&MockObject $userRepository */
        $userRepository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $userRepository->expects(self::once())->method('getById')->with(10)->willReturn($target);
        $userRepository->expects(self::once())->method('save')->with($target);

        /** @var AdminUserAuditReadRepositoryInterface&MockObject $auditRepository */
        $auditRepository = $this->createMock(AdminUserAuditReadRepositoryInterface::class);
        $auditRepository->expects(self::once())->method('countRecentActionsByActor')->willReturn(0);

        $service = new GenerateResetLinkApplicationService($userRepository, $auditRepository, new TemporaryLinkPolicy());
        $result = $service->generate(10, $actor);

        self::assertSame(GenerateResetLinkStatus::GENERATED, $result->getStatus());
        self::assertNotNull($target->getResetPasswordTokenHash());
        self::assertNotNull($target->getResetPasswordExpiresAt());
        self::assertNotNull($target->getResetPasswordRequestedAt());
        self::assertNotNull($result->getToken());
        self::assertSame(64, strlen((string) $result->getToken()));
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, array $roles): UserEntity
    {
        return new UserEntity()
            ->setEmail($email)
            ->setPassword('hash')
            ->setRoles($roles);
    }
}
