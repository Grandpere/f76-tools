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

namespace App\Tests\Unit\Identity\Application\ResendVerification;

use App\Entity\UserEntity;
use App\Identity\Application\Common\IdentityWritePersistenceInterface;
use App\Identity\Application\ResendVerification\ResendVerificationRequestApplicationService;
use App\Identity\Application\ResendVerification\ResendVerificationUserRepositoryInterface;
use App\Identity\Application\Security\TemporaryLinkPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResendVerificationRequestApplicationServiceTest extends TestCase
{
    private ResendVerificationUserRepositoryInterface&MockObject $repository;
    private IdentityWritePersistenceInterface&MockObject $persistence;
    private TemporaryLinkPolicy $temporaryLinkPolicy;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ResendVerificationUserRepositoryInterface::class);
        $this->persistence = $this->createMock(IdentityWritePersistenceInterface::class);
        $this->temporaryLinkPolicy = new TemporaryLinkPolicy();
    }

    public function testRequestNoActionForInvalidEmail(): void
    {
        $this->repository->expects(self::never())->method('findOneByEmail');
        $this->persistence->expects(self::never())->method('flush');

        $result = $this->service()->request('invalid', new DateTimeImmutable());

        self::assertFalse($result->isTokenIssued());
    }

    public function testRequestNoActionWhenUserNotFound(): void
    {
        $this->repository->method('findOneByEmail')->willReturn(null);
        $this->persistence->expects(self::never())->method('flush');

        $result = $this->service()->request('unknown@example.com', new DateTimeImmutable());

        self::assertFalse($result->isTokenIssued());
    }

    public function testRequestNoActionForAlreadyVerifiedUser(): void
    {
        $user = new UserEntity()
            ->setEmail('verified@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(true);

        $this->repository->method('findOneByEmail')->willReturn($user);
        $this->persistence->expects(self::never())->method('flush');

        $result = $this->service()->request('verified@example.com', new DateTimeImmutable());

        self::assertFalse($result->isTokenIssued());
    }

    public function testRequestNoActionWhenCooldownActive(): void
    {
        $user = new UserEntity()
            ->setEmail('cooldown@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(false)
            ->setEmailVerificationRequestedAt(new DateTimeImmutable('-10 seconds'));

        $this->repository->method('findOneByEmail')->willReturn($user);
        $this->persistence->expects(self::never())->method('flush');

        $result = $this->service()->request('cooldown@example.com', new DateTimeImmutable());

        self::assertFalse($result->isTokenIssued());
    }

    public function testRequestIssuesTokenAndFlushes(): void
    {
        $user = new UserEntity()
            ->setEmail('resend@example.com')
            ->setPassword('hash')
            ->setIsEmailVerified(false)
            ->setEmailVerificationRequestedAt(new DateTimeImmutable('-2 hours'));

        $this->repository->method('findOneByEmail')->willReturn($user);
        $this->persistence->expects(self::once())->method('flush');

        $result = $this->service()->request('resend@example.com', new DateTimeImmutable());

        self::assertTrue($result->isTokenIssued());
        self::assertSame('resend@example.com', $result->getEmail());
        self::assertNotNull($result->getPlainToken());
        self::assertNotNull($user->getEmailVerificationTokenHash());
        self::assertNotNull($user->getEmailVerificationExpiresAt());
        self::assertNotNull($user->getEmailVerificationRequestedAt());
    }

    private function service(): ResendVerificationRequestApplicationService
    {
        return new ResendVerificationRequestApplicationService(
            $this->repository,
            $this->persistence,
            $this->temporaryLinkPolicy,
        );
    }
}
