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

namespace App\Tests\Unit\Identity\Application\Security;

use App\Identity\Application\Oidc\GoogleOidcIdentityReadRepository;
use App\Identity\Application\Oidc\GoogleOidcIdentityWriteRepository;
use App\Identity\Application\Security\UnlinkOwnGoogleIdentityApplicationService;
use App\Identity\Application\Security\UnlinkOwnGoogleIdentityResult;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UnlinkOwnGoogleIdentityApplicationServiceTest extends TestCase
{
    private GoogleOidcIdentityReadRepository&MockObject $identityReadRepository;
    private GoogleOidcIdentityWriteRepository&MockObject $identityWriteRepository;

    protected function setUp(): void
    {
        $this->identityReadRepository = $this->createMock(GoogleOidcIdentityReadRepository::class);
        $this->identityWriteRepository = $this->createMock(GoogleOidcIdentityWriteRepository::class);
    }

    public function testUnlinkReturnsIdentityNotFoundWhenUserHasNoGoogleIdentity(): void
    {
        $user = new UserEntity()
            ->setEmail('test@example.com')
            ->setPassword('hash');

        $this->identityReadRepository->expects(self::once())
            ->method('findOneByUserAndProvider')
            ->with($user, 'google')
            ->willReturn(null);
        $this->identityWriteRepository->expects(self::never())->method('delete');

        $result = $this->service()->unlink($user);

        self::assertSame(UnlinkOwnGoogleIdentityResult::GOOGLE_IDENTITY_NOT_FOUND, $result);
    }

    public function testUnlinkRequiresLocalPasswordBeforeRemovingGoogleIdentity(): void
    {
        $user = new UserEntity()
            ->setEmail('test@example.com')
            ->setPassword('hash')
            ->setHasLocalPassword(false);
        $identity = new UserIdentityEntity()
            ->setUser($user)
            ->setProvider('google')
            ->setProviderUserId('sub-1');

        $this->identityReadRepository->expects(self::once())
            ->method('findOneByUserAndProvider')
            ->with($user, 'google')
            ->willReturn($identity);
        $this->identityWriteRepository->expects(self::never())->method('delete');

        $result = $this->service()->unlink($user);

        self::assertSame(UnlinkOwnGoogleIdentityResult::LOCAL_PASSWORD_REQUIRED, $result);
    }

    public function testUnlinkDeletesIdentityWhenPresentAndLocalPasswordEnabled(): void
    {
        $user = new UserEntity()
            ->setEmail('test@example.com')
            ->setPassword('hash')
            ->setHasLocalPassword(true);
        $identity = new UserIdentityEntity()
            ->setUser($user)
            ->setProvider('google')
            ->setProviderUserId('sub-2');

        $this->identityReadRepository->expects(self::once())
            ->method('findOneByUserAndProvider')
            ->with($user, 'google')
            ->willReturn($identity);
        $this->identityWriteRepository->expects(self::once())
            ->method('delete')
            ->with($identity);

        $result = $this->service()->unlink($user);

        self::assertSame(UnlinkOwnGoogleIdentityResult::UNLINKED, $result);
    }

    private function service(): UnlinkOwnGoogleIdentityApplicationService
    {
        return new UnlinkOwnGoogleIdentityApplicationService(
            $this->identityReadRepository,
            $this->identityWriteRepository,
        );
    }
}
