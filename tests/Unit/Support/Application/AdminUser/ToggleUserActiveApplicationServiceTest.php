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

use App\Identity\Domain\Entity\UserEntity;
use App\Support\Application\AdminUser\AdminUserManagementWriteRepository;
use App\Support\Application\AdminUser\ToggleUserActiveApplicationService;
use App\Support\Application\AdminUser\ToggleUserActiveResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ToggleUserActiveApplicationServiceTest extends TestCase
{
    public function testToggleReturnsUserNotFoundWhenTargetDoesNotExist(): void
    {
        $actor = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_ADMIN']);

        /** @var AdminUserManagementWriteRepository&MockObject $repository */
        $repository = $this->createMock(AdminUserManagementWriteRepository::class);
        $repository->expects(self::once())->method('getById')->with(10)->willReturn(null);
        $repository->expects(self::never())->method('save');

        $service = new ToggleUserActiveApplicationService($repository);

        $result = $service->toggle(10, $actor);

        self::assertSame(ToggleUserActiveResult::USER_NOT_FOUND, $result);
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

        /** @var AdminUserManagementWriteRepository&MockObject $repository */
        $repository = $this->createMock(AdminUserManagementWriteRepository::class);
        $repository->expects(self::once())->method('getById')->with(42)->willReturn($target);
        $repository->expects(self::never())->method('save');

        $service = new ToggleUserActiveApplicationService($repository);

        $result = $service->toggle(42, $actor);

        self::assertSame(ToggleUserActiveResult::CANNOT_CHANGE_SELF, $result);
    }

    public function testToggleReturnsUpdatedAndPersistsEntity(): void
    {
        $actor = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_ADMIN']);
        $target = new UserEntity()
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true);

        $actorId = new ReflectionProperty(UserEntity::class, 'id');
        $actorId->setValue($actor, 1);

        $targetId = new ReflectionProperty(UserEntity::class, 'id');
        $targetId->setValue($target, 2);

        /** @var AdminUserManagementWriteRepository&MockObject $repository */
        $repository = $this->createMock(AdminUserManagementWriteRepository::class);
        $repository->expects(self::once())->method('getById')->with(2)->willReturn($target);
        $repository->expects(self::once())->method('save')->with($target);

        $service = new ToggleUserActiveApplicationService($repository);

        $result = $service->toggle(2, $actor);

        self::assertSame(ToggleUserActiveResult::UPDATED, $result);
        self::assertFalse($target->isActive());
    }
}
