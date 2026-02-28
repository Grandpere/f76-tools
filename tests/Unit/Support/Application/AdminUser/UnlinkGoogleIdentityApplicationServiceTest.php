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

use App\Identity\Application\Oidc\GoogleOidcIdentityReadRepository;
use App\Identity\Application\Oidc\GoogleOidcIdentityWriteRepository;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;
use App\Support\Application\AdminUser\AdminUserManagementWriteRepositoryInterface;
use App\Support\Application\AdminUser\UnlinkGoogleIdentityApplicationService;
use App\Support\Application\AdminUser\UnlinkGoogleIdentityResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UnlinkGoogleIdentityApplicationServiceTest extends TestCase
{
    private AdminUserManagementWriteRepositoryInterface&MockObject $userRepository;
    private GoogleOidcIdentityReadRepository&MockObject $identityReadRepository;
    private GoogleOidcIdentityWriteRepository&MockObject $identityWriteRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
        $this->identityReadRepository = $this->createMock(GoogleOidcIdentityReadRepository::class);
        $this->identityWriteRepository = $this->createMock(GoogleOidcIdentityWriteRepository::class);
    }

    public function testUnlinkReturnsUserNotFoundWhenTargetDoesNotExist(): void
    {
        $this->userRepository->expects(self::once())
            ->method('getById')
            ->with(100)
            ->willReturn(null);
        $this->identityWriteRepository->expects(self::never())->method('delete');

        $result = $this->service()->unlink(100);

        self::assertSame(UnlinkGoogleIdentityResult::USER_NOT_FOUND, $result);
    }

    public function testUnlinkReturnsIdentityNotFoundWhenUserHasNoGoogleIdentity(): void
    {
        $user = new UserEntity()
            ->setEmail('test@example.com')
            ->setPassword('hash');

        $this->userRepository->expects(self::once())->method('getById')->willReturn($user);
        $this->identityReadRepository->expects(self::once())
            ->method('findOneByUserAndProvider')
            ->with($user, 'google')
            ->willReturn(null);
        $this->identityWriteRepository->expects(self::never())->method('delete');

        $result = $this->service()->unlink(10);

        self::assertSame(UnlinkGoogleIdentityResult::GOOGLE_IDENTITY_NOT_FOUND, $result);
    }

    public function testUnlinkDeletesIdentityWhenPresent(): void
    {
        $user = new UserEntity()
            ->setEmail('test@example.com')
            ->setPassword('hash');
        $identity = new UserIdentityEntity()
            ->setUser($user)
            ->setProvider('google')
            ->setProviderUserId('sub-1');

        $this->userRepository->expects(self::once())->method('getById')->willReturn($user);
        $this->identityReadRepository->expects(self::once())
            ->method('findOneByUserAndProvider')
            ->with($user, 'google')
            ->willReturn($identity);
        $this->identityWriteRepository->expects(self::once())
            ->method('delete')
            ->with($identity);

        $result = $this->service()->unlink(10);

        self::assertSame(UnlinkGoogleIdentityResult::UNLINKED, $result);
    }

    public function testHasGoogleIdentityReturnsTrueWhenPresent(): void
    {
        $user = new UserEntity()
            ->setEmail('test@example.com')
            ->setPassword('hash');
        $identity = new UserIdentityEntity()
            ->setUser($user)
            ->setProvider('google')
            ->setProviderUserId('sub-1');

        $this->identityReadRepository->expects(self::once())
            ->method('findOneByUserAndProvider')
            ->with($user, 'google')
            ->willReturn($identity);

        self::assertTrue($this->service()->hasGoogleIdentity($user));
    }

    private function service(): UnlinkGoogleIdentityApplicationService
    {
        return new UnlinkGoogleIdentityApplicationService(
            $this->userRepository,
            $this->identityReadRepository,
            $this->identityWriteRepository,
        );
    }
}
