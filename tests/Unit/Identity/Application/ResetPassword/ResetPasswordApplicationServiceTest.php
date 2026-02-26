<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\ResetPassword;

use App\Entity\UserEntity;
use App\Identity\Application\Common\IdentityPasswordHasherInterface;
use App\Identity\Application\Common\IdentityWritePersistenceInterface;
use App\Identity\Application\ResetPassword\ResetPasswordApplicationService;
use App\Identity\Application\ResetPassword\ResetPasswordResult;
use App\Identity\Application\ResetPassword\ResetPasswordUserRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResetPasswordApplicationServiceTest extends TestCase
{
    private ResetPasswordUserRepositoryInterface&MockObject $repository;
    private IdentityPasswordHasherInterface&MockObject $passwordHasher;
    private IdentityWritePersistenceInterface&MockObject $persistence;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ResetPasswordUserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(IdentityPasswordHasherInterface::class);
        $this->persistence = $this->createMock(IdentityWritePersistenceInterface::class);
    }

    public function testCanResetTokenReturnsFalseForBlankToken(): void
    {
        $service = $this->createService();

        self::assertFalse($service->canResetToken(' ', new \DateTimeImmutable()));
    }

    public function testResetReturnsInvalidWhenTokenNotFound(): void
    {
        $this->repository->method('findOneByResetPasswordTokenHash')->willReturn(null);

        $service = $this->createService();

        self::assertSame(
            ResetPasswordResult::INVALID_OR_EXPIRED,
            $service->resetByPlainToken('token', 'password123', 'password123', new \DateTimeImmutable()),
        );
    }

    public function testResetReturnsPasswordTooShort(): void
    {
        $this->repository->method('findOneByResetPasswordTokenHash')->willReturn($this->validUser());

        $service = $this->createService();

        self::assertSame(
            ResetPasswordResult::PASSWORD_TOO_SHORT,
            $service->resetByPlainToken('token', 'short', 'short', new \DateTimeImmutable()),
        );
    }

    public function testResetReturnsPasswordMismatch(): void
    {
        $this->repository->method('findOneByResetPasswordTokenHash')->willReturn($this->validUser());

        $service = $this->createService();

        self::assertSame(
            ResetPasswordResult::PASSWORD_MISMATCH,
            $service->resetByPlainToken('token', 'password123', 'password321', new \DateTimeImmutable()),
        );
    }

    public function testResetSuccessHashesPasswordAndFlushes(): void
    {
        $user = $this->validUser();

        $this->repository->method('findOneByResetPasswordTokenHash')->willReturn($user);
        $this->passwordHasher
            ->expects(self::once())
            ->method('hash')
            ->with($user, 'password123')
            ->willReturn('hashed_password');
        $this->persistence->expects(self::once())->method('flush');

        $service = $this->createService();

        self::assertSame(
            ResetPasswordResult::SUCCESS,
            $service->resetByPlainToken('token', 'password123', 'password123', new \DateTimeImmutable()),
        );
        self::assertSame('hashed_password', $user->getPassword());
        self::assertNull($user->getResetPasswordTokenHash());
        self::assertNull($user->getResetPasswordExpiresAt());
        self::assertNull($user->getResetPasswordRequestedAt());
    }

    private function createService(): ResetPasswordApplicationService
    {
        return new ResetPasswordApplicationService(
            $this->repository,
            $this->passwordHasher,
            $this->persistence,
        );
    }

    private function validUser(): UserEntity
    {
        return (new UserEntity())
            ->setEmail('a@b.c')
            ->setPassword('existing')
            ->setResetPasswordTokenHash('hash')
            ->setResetPasswordExpiresAt(new \DateTimeImmutable('+1 hour'))
            ->setResetPasswordRequestedAt(new \DateTimeImmutable('-1 hour'));
    }
}
