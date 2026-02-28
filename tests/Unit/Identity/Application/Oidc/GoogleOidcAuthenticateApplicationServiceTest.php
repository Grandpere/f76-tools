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

namespace App\Tests\Unit\Identity\Application\Oidc;

use App\Identity\Application\Common\IdentityPasswordHasherInterface;
use App\Identity\Application\Common\IdentityWritePersistenceInterface;
use App\Identity\Application\Oidc\GoogleOidcAuthenticateApplicationService;
use App\Identity\Application\Oidc\GoogleOidcAuthenticationAction;
use App\Identity\Application\Oidc\GoogleOidcAuthenticationException;
use App\Identity\Application\Oidc\GoogleOidcIdentityReadRepository;
use App\Identity\Application\Oidc\GoogleOidcIdentityWriteRepository;
use App\Identity\Application\Oidc\GoogleOidcUserProfile;
use App\Identity\Application\User\UserByEmailFinder;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GoogleOidcAuthenticateApplicationServiceTest extends TestCase
{
    private GoogleOidcIdentityReadRepository&MockObject $identityReadRepository;
    private GoogleOidcIdentityWriteRepository&MockObject $identityWriteRepository;
    private UserByEmailFinder&MockObject $userByEmailFinder;
    private IdentityPasswordHasherInterface&MockObject $passwordHasher;
    private IdentityWritePersistenceInterface&MockObject $persistence;

    protected function setUp(): void
    {
        $this->identityReadRepository = $this->createMock(GoogleOidcIdentityReadRepository::class);
        $this->identityWriteRepository = $this->createMock(GoogleOidcIdentityWriteRepository::class);
        $this->userByEmailFinder = $this->createMock(UserByEmailFinder::class);
        $this->passwordHasher = $this->createMock(IdentityPasswordHasherInterface::class);
        $this->persistence = $this->createMock(IdentityWritePersistenceInterface::class);
    }

    public function testReturnsExistingIdentityUserWhenIdentityAlreadyLinked(): void
    {
        $user = new UserEntity()
            ->setEmail('linked@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(false)
            ->setEmailVerificationTokenHash('token')
            ->setEmailVerificationRequestedAt(new DateTimeImmutable('-1 hour'))
            ->setEmailVerificationExpiresAt(new DateTimeImmutable('+1 hour'));

        $identity = new UserIdentityEntity()
            ->setUser($user)
            ->setProvider('google')
            ->setProviderUserId('google-sub-1')
            ->setProviderEmail('linked@example.com');

        $this->identityReadRepository->expects(self::once())
            ->method('findOneByProviderAndProviderUserId')
            ->with('google', 'google-sub-1')
            ->willReturn($identity);
        $this->userByEmailFinder->expects(self::never())->method('findOneByEmail');
        $this->identityWriteRepository->expects(self::never())->method('save');
        $this->persistence->expects(self::once())->method('flush');

        $result = $this->service()->authenticate(new GoogleOidcUserProfile('google-sub-1', 'linked@example.com', true));

        self::assertSame(GoogleOidcAuthenticationAction::IDENTITY_FOUND, $result->action());
        self::assertSame($user, $result->user());
        self::assertTrue($user->isEmailVerified());
        self::assertNull($user->getEmailVerificationTokenHash());
    }

    public function testAutoLinksExistingUserByEmail(): void
    {
        $user = new UserEntity()
            ->setEmail('known@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(false);

        $this->identityReadRepository->expects(self::once())
            ->method('findOneByProviderAndProviderUserId')
            ->willReturn(null);
        $this->userByEmailFinder->expects(self::once())
            ->method('findOneByEmail')
            ->with('known@example.com')
            ->willReturn($user);
        $this->identityReadRepository->expects(self::once())
            ->method('findOneByUserAndProvider')
            ->with($user, 'google')
            ->willReturn(null);
        $this->identityWriteRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (mixed $identity): bool {
                if (!$identity instanceof UserIdentityEntity) {
                    return false;
                }

                return 'google' === $identity->getProvider()
                    && 'google-sub-2' === $identity->getProviderUserId()
                    && 'known@example.com' === $identity->getProviderEmail();
            }));
        $this->persistence->expects(self::once())->method('flush');

        $result = $this->service()->authenticate(new GoogleOidcUserProfile('google-sub-2', 'known@example.com', true));

        self::assertSame(GoogleOidcAuthenticationAction::AUTO_LINKED, $result->action());
        self::assertSame($user, $result->user());
        self::assertTrue($user->isEmailVerified());
    }

    public function testCreatesUserAndIdentityWhenEmailDoesNotExist(): void
    {
        $this->identityReadRepository->expects(self::once())
            ->method('findOneByProviderAndProviderUserId')
            ->willReturn(null);
        $this->userByEmailFinder->expects(self::once())
            ->method('findOneByEmail')
            ->with('new@example.com')
            ->willReturn(null);
        $this->passwordHasher->expects(self::once())
            ->method('hash')
            ->willReturn('generated_hash');
        $this->persistence->expects(self::once())->method('persist')->with(self::isInstanceOf(UserEntity::class));
        $this->identityWriteRepository->expects(self::once())->method('save')->with(self::isInstanceOf(UserIdentityEntity::class));
        $this->persistence->expects(self::once())->method('flush');

        $result = $this->service()->authenticate(new GoogleOidcUserProfile('google-sub-3', 'new@example.com', true));

        self::assertSame(GoogleOidcAuthenticationAction::USER_CREATED, $result->action());
        self::assertSame('new@example.com', $result->user()->getEmail());
        self::assertSame('generated_hash', $result->user()->getPassword());
        self::assertTrue($result->user()->isEmailVerified());
    }

    public function testThrowsWhenGoogleEmailIsNotVerified(): void
    {
        $this->identityReadRepository->expects(self::never())->method('findOneByProviderAndProviderUserId');
        $this->persistence->expects(self::never())->method('flush');

        $this->expectException(GoogleOidcAuthenticationException::class);
        $this->expectExceptionMessage('security.oidc.flash.email_not_verified');

        $this->service()->authenticate(new GoogleOidcUserProfile('google-sub-4', 'user@example.com', false));
    }

    private function service(): GoogleOidcAuthenticateApplicationService
    {
        return new GoogleOidcAuthenticateApplicationService(
            $this->identityReadRepository,
            $this->identityWriteRepository,
            $this->userByEmailFinder,
            $this->passwordHasher,
            $this->persistence,
        );
    }
}
