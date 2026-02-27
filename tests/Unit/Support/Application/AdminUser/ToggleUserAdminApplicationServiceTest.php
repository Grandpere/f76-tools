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
use App\Support\Application\AdminUser\AdminUserManagementWriteRepositoryInterface;
use App\Support\Application\AdminUser\ToggleUserAdminApplicationService;
use App\Support\Application\AdminUser\ToggleUserAdminResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ToggleUserAdminApplicationServiceTest extends TestCase
{
    public function testToggleReturnsActorRequiredWhenActorIsNotUserEntity(): void
    {
        /** @var AdminUserManagementWriteRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $repository->expects(self::never())->method('getById');
        $repository->expects(self::never())->method('save');

        $service = new ToggleUserAdminApplicationService($repository);

        $result = $service->toggle(10, null);

        self::assertSame(ToggleUserAdminResult::ACTOR_REQUIRED, $result);
    }

    public function testToggleReturnsUserNotFoundWhenTargetDoesNotExist(): void
    {
        $actor = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_ADMIN']);

        /** @var AdminUserManagementWriteRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $repository->expects(self::once())->method('getById')->with(10)->willReturn(null);
        $repository->expects(self::never())->method('save');

        $service = new ToggleUserAdminApplicationService($repository);

        $result = $service->toggle(10, $actor);

        self::assertSame(ToggleUserAdminResult::USER_NOT_FOUND, $result);
    }

    public function testToggleReturnsCannotChangeSelfWhenActorAndTargetAreSameUser(): void
    {
        $actor = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_ADMIN']);
        $target = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_ADMIN']);

        $reflection = new ReflectionProperty(UserEntity::class, 'id');
        $reflection->setValue($actor, 42);
        $reflection->setValue($target, 42);

        /** @var AdminUserManagementWriteRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $repository->expects(self::once())->method('getById')->with(42)->willReturn($target);
        $repository->expects(self::never())->method('save');

        $service = new ToggleUserAdminApplicationService($repository);

        $result = $service->toggle(42, $actor);

        self::assertSame(ToggleUserAdminResult::CANNOT_CHANGE_SELF, $result);
    }

    public function testToggleAddsAdminRoleWhenMissing(): void
    {
        $actor = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_ADMIN']);
        $target = new UserEntity()
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);

        $actorId = new ReflectionProperty(UserEntity::class, 'id');
        $actorId->setValue($actor, 1);

        $targetId = new ReflectionProperty(UserEntity::class, 'id');
        $targetId->setValue($target, 2);

        /** @var AdminUserManagementWriteRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $repository->expects(self::once())->method('getById')->with(2)->willReturn($target);
        $repository->expects(self::once())->method('save')->with($target);

        $service = new ToggleUserAdminApplicationService($repository);

        $result = $service->toggle(2, $actor);

        self::assertSame(ToggleUserAdminResult::UPDATED, $result);
        self::assertContains('ROLE_ADMIN', $target->getRoles());
    }

    public function testToggleRemovesAdminRoleWhenAlreadyPresent(): void
    {
        $actor = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_ADMIN']);
        $target = new UserEntity()
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $actorId = new ReflectionProperty(UserEntity::class, 'id');
        $actorId->setValue($actor, 1);

        $targetId = new ReflectionProperty(UserEntity::class, 'id');
        $targetId->setValue($target, 2);

        /** @var AdminUserManagementWriteRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $repository->expects(self::once())->method('getById')->with(2)->willReturn($target);
        $repository->expects(self::once())->method('save')->with($target);

        $service = new ToggleUserAdminApplicationService($repository);

        $result = $service->toggle(2, $actor);

        self::assertSame(ToggleUserAdminResult::UPDATED, $result);
        self::assertNotContains('ROLE_ADMIN', $target->getRoles());
    }
}
