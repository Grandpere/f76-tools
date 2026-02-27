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

namespace App\Tests\Unit\Identity\Application\Registration;

use App\Identity\Application\Common\IdentityPasswordHasherInterface;
use App\Identity\Application\Common\IdentityWritePersistenceInterface;
use App\Identity\Application\Registration\RegisterUserApplicationService;
use App\Identity\Application\Registration\RegisterUserStatus;
use App\Identity\Application\Registration\RegistrationUserRepositoryInterface;
use App\Identity\Application\Security\TemporaryLinkPolicy;
use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegisterUserApplicationServiceTest extends TestCase
{
    private RegistrationUserRepositoryInterface&MockObject $repository;
    private IdentityPasswordHasherInterface&MockObject $passwordHasher;
    private IdentityWritePersistenceInterface&MockObject $persistence;
    private TemporaryLinkPolicy $temporaryLinkPolicy;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RegistrationUserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(IdentityPasswordHasherInterface::class);
        $this->persistence = $this->createMock(IdentityWritePersistenceInterface::class);
        $this->temporaryLinkPolicy = new TemporaryLinkPolicy();
    }

    public function testInvalidEmailReturnsInvalidStatus(): void
    {
        $result = $this->service()->register('invalid', 'password123', 'password123', new DateTimeImmutable());

        self::assertSame(RegisterUserStatus::INVALID_EMAIL, $result->getStatus());
    }

    public function testShortPasswordReturnsPasswordTooShortStatus(): void
    {
        $result = $this->service()->register('user@example.com', 'short', 'short', new DateTimeImmutable());

        self::assertSame(RegisterUserStatus::PASSWORD_TOO_SHORT, $result->getStatus());
    }

    public function testPasswordMismatchReturnsMismatchStatus(): void
    {
        $result = $this->service()->register('user@example.com', 'password123', 'password321', new DateTimeImmutable());

        self::assertSame(RegisterUserStatus::PASSWORD_MISMATCH, $result->getStatus());
    }

    public function testExistingEmailReturnsEmailExistsStatus(): void
    {
        $existing = new UserEntity()
            ->setEmail('user@example.com')
            ->setPassword('hash');

        $this->repository->method('findOneByEmail')->willReturn($existing);

        $result = $this->service()->register('user@example.com', 'password123', 'password123', new DateTimeImmutable());

        self::assertSame(RegisterUserStatus::EMAIL_EXISTS, $result->getStatus());
    }

    public function testSuccessPersistsUserAndReturnsToken(): void
    {
        $this->repository->method('findOneByEmail')->willReturn(null);
        $this->passwordHasher->expects(self::once())->method('hash')->willReturn('hashed_password');
        $this->persistence->expects(self::once())->method('persist');
        $this->persistence->expects(self::once())->method('flush');

        $result = $this->service()->register('user@example.com', 'password123', 'password123', new DateTimeImmutable());

        self::assertSame(RegisterUserStatus::SUCCESS, $result->getStatus());
        self::assertSame('user@example.com', $result->getEmail());
        self::assertNotNull($result->getPlainVerificationToken());
        self::assertSame(64, strlen((string) $result->getPlainVerificationToken()));
    }

    private function service(): RegisterUserApplicationService
    {
        return new RegisterUserApplicationService(
            $this->repository,
            $this->passwordHasher,
            $this->persistence,
            $this->temporaryLinkPolicy,
        );
    }
}
