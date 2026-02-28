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

namespace App\Tests\Unit\Identity\Application\VerifyEmail;

use App\Identity\Application\Common\IdentityWritePersistence;
use App\Identity\Application\VerifyEmail\VerifyEmailApplicationService;
use App\Identity\Application\VerifyEmail\VerifyEmailUserRepository;
use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class VerifyEmailApplicationServiceTest extends TestCase
{
    private VerifyEmailUserRepository&MockObject $repository;
    private IdentityWritePersistence&MockObject $persistence;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(VerifyEmailUserRepository::class);
        $this->persistence = $this->createMock(IdentityWritePersistence::class);
    }

    public function testVerifyReturnsFalseWhenTokenIsBlank(): void
    {
        $this->repository->expects(self::never())->method('findOneByEmailVerificationTokenHash');
        $this->persistence->expects(self::never())->method('flush');

        $service = new VerifyEmailApplicationService($this->repository, $this->persistence);

        self::assertFalse($service->verifyByPlainToken('   ', new DateTimeImmutable()));
    }

    public function testVerifyReturnsFalseWhenUserNotFound(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findOneByEmailVerificationTokenHash')
            ->willReturn(null);
        $this->persistence->expects(self::never())->method('flush');

        $service = new VerifyEmailApplicationService($this->repository, $this->persistence);

        self::assertFalse($service->verifyByPlainToken('token', new DateTimeImmutable()));
    }

    public function testVerifyReturnsFalseWhenTokenExpired(): void
    {
        $user = new UserEntity()
            ->setEmail('a@b.c')
            ->setPassword('hash')
            ->setEmailVerificationExpiresAt(new DateTimeImmutable('-1 second'));

        $this->repository
            ->expects(self::once())
            ->method('findOneByEmailVerificationTokenHash')
            ->willReturn($user);
        $this->persistence->expects(self::never())->method('flush');

        $service = new VerifyEmailApplicationService($this->repository, $this->persistence);

        self::assertFalse($service->verifyByPlainToken('token', new DateTimeImmutable()));
    }

    public function testVerifyMarksUserAsVerifiedAndFlushes(): void
    {
        $user = new UserEntity()
            ->setEmail('a@b.c')
            ->setPassword('hash')
            ->setIsEmailVerified(false)
            ->setEmailVerificationTokenHash('hash')
            ->setEmailVerificationExpiresAt(new DateTimeImmutable('+1 hour'))
            ->setEmailVerificationRequestedAt(new DateTimeImmutable('-1 hour'));

        $this->repository
            ->expects(self::once())
            ->method('findOneByEmailVerificationTokenHash')
            ->willReturn($user);
        $this->persistence->expects(self::once())->method('flush');

        $service = new VerifyEmailApplicationService($this->repository, $this->persistence);

        self::assertTrue($service->verifyByPlainToken('token', new DateTimeImmutable()));
        self::assertTrue($user->isEmailVerified());
        self::assertNull($user->getEmailVerificationTokenHash());
        self::assertNull($user->getEmailVerificationExpiresAt());
        self::assertNull($user->getEmailVerificationRequestedAt());
    }
}
