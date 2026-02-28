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
use App\Support\Application\AdminUser\AdminUserManagementWriteRepositoryInterface;
use App\Support\Application\AdminUser\ForceVerifyEmailApplicationService;
use App\Support\Application\AdminUser\ForceVerifyEmailResult;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ForceVerifyEmailApplicationServiceTest extends TestCase
{
    private AdminUserManagementWriteRepositoryInterface&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(AdminUserManagementWriteRepositoryInterface::class);
    }

    public function testVerifyReturnsUserNotFoundWhenTargetDoesNotExist(): void
    {
        $this->userRepository->expects(self::once())->method('getById')->with(10)->willReturn(null);
        $this->userRepository->expects(self::never())->method('save');

        $result = $this->service()->verify(10);

        self::assertSame(ForceVerifyEmailResult::USER_NOT_FOUND, $result);
    }

    public function testVerifyReturnsAlreadyVerifiedWhenUserAlreadyVerified(): void
    {
        $user = new UserEntity()
            ->setEmail('already@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(true);

        $this->userRepository->expects(self::once())->method('getById')->with(20)->willReturn($user);
        $this->userRepository->expects(self::never())->method('save');

        $result = $this->service()->verify(20);

        self::assertSame(ForceVerifyEmailResult::ALREADY_VERIFIED, $result);
    }

    public function testVerifyMarksEmailVerifiedAndClearsVerificationTokens(): void
    {
        $user = new UserEntity()
            ->setEmail('verify@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(false)
            ->setEmailVerificationTokenHash(hash('sha256', 'token'))
            ->setEmailVerificationRequestedAt(new DateTimeImmutable('-1 minute'))
            ->setEmailVerificationExpiresAt(new DateTimeImmutable('+1 hour'));

        $this->userRepository->expects(self::once())->method('getById')->with(30)->willReturn($user);
        $this->userRepository->expects(self::once())->method('save')->with($user);

        $result = $this->service()->verify(30);

        self::assertSame(ForceVerifyEmailResult::VERIFIED, $result);
        self::assertTrue($user->isEmailVerified());
        self::assertNull($user->getEmailVerificationTokenHash());
        self::assertNull($user->getEmailVerificationRequestedAt());
        self::assertNull($user->getEmailVerificationExpiresAt());
    }

    private function service(): ForceVerifyEmailApplicationService
    {
        return new ForceVerifyEmailApplicationService($this->userRepository);
    }
}
