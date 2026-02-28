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

namespace App\Tests\Unit\Identity\Application\ChangePassword;

use App\Identity\Application\ChangePassword\ChangePasswordApplicationService;
use App\Identity\Application\ChangePassword\ChangePasswordRequest;
use App\Identity\Application\ChangePassword\ChangePasswordResult;
use App\Identity\Application\Common\IdentityPasswordHasher;
use App\Identity\Application\Common\IdentityPasswordVerifier;
use App\Identity\Application\Common\IdentityWritePersistence;
use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ChangePasswordApplicationServiceTest extends TestCase
{
    private IdentityPasswordVerifier&MockObject $passwordVerifier;
    private IdentityPasswordHasher&MockObject $passwordHasher;
    private IdentityWritePersistence&MockObject $persistence;

    protected function setUp(): void
    {
        $this->passwordVerifier = $this->createMock(IdentityPasswordVerifier::class);
        $this->passwordHasher = $this->createMock(IdentityPasswordHasher::class);
        $this->persistence = $this->createMock(IdentityWritePersistence::class);
    }

    public function testChangeReturnsCurrentPasswordInvalidWhenVerifierFails(): void
    {
        $user = $this->user();
        $this->passwordVerifier
            ->expects(self::once())
            ->method('isValid')
            ->with($user, 'wrong-password')
            ->willReturn(false);

        $result = $this->service()->change($user, ChangePasswordRequest::fromRaw('wrong-password', 'new-password', 'new-password'));

        self::assertSame(ChangePasswordResult::CURRENT_PASSWORD_INVALID, $result);
    }

    public function testChangeReturnsTooShortWhenNewPasswordIsTooShort(): void
    {
        $user = $this->user();
        $this->passwordVerifier->method('isValid')->willReturn(true);

        $result = $this->service()->change($user, ChangePasswordRequest::fromRaw('current-password', 'short', 'short'));

        self::assertSame(ChangePasswordResult::PASSWORD_TOO_SHORT, $result);
    }

    public function testChangeReturnsMismatchWhenConfirmationIsDifferent(): void
    {
        $user = $this->user();
        $this->passwordVerifier->method('isValid')->willReturn(true);

        $result = $this->service()->change($user, ChangePasswordRequest::fromRaw('current-password', 'new-password', 'new-password-different'));

        self::assertSame(ChangePasswordResult::PASSWORD_MISMATCH, $result);
    }

    public function testChangeSuccessUpdatesPasswordAndClearsResetFields(): void
    {
        $user = $this->user()
            ->setResetPasswordTokenHash('token-hash')
            ->setResetPasswordExpiresAt(new DateTimeImmutable('+1 hour'))
            ->setResetPasswordRequestedAt(new DateTimeImmutable('-1 minute'))
            ->setHasLocalPassword(false);

        $this->passwordVerifier->expects(self::once())->method('isValid')->with($user, 'current-password')->willReturn(true);
        $this->passwordHasher->expects(self::once())->method('hash')->with($user, 'new-password')->willReturn('new-password-hash');
        $this->persistence->expects(self::once())->method('flush');

        $result = $this->service()->change($user, ChangePasswordRequest::fromRaw('current-password', 'new-password', 'new-password'));

        self::assertSame(ChangePasswordResult::SUCCESS, $result);
        self::assertSame('new-password-hash', $user->getPassword());
        self::assertTrue($user->hasLocalPassword());
        self::assertNull($user->getResetPasswordTokenHash());
        self::assertNull($user->getResetPasswordExpiresAt());
        self::assertNull($user->getResetPasswordRequestedAt());
    }

    private function service(): ChangePasswordApplicationService
    {
        return new ChangePasswordApplicationService(
            $this->passwordVerifier,
            $this->passwordHasher,
            $this->persistence,
        );
    }

    private function user(): UserEntity
    {
        return new UserEntity()
            ->setEmail('change-password@example.com')
            ->setPassword('existing-password-hash');
    }
}
