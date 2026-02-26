<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\ForgotPassword;

use App\Entity\UserEntity;
use App\Identity\Application\Common\IdentityWritePersistenceInterface;
use App\Identity\Application\ForgotPassword\ForgotPasswordRequestApplicationService;
use App\Identity\Application\ForgotPassword\ForgotPasswordUserRepositoryInterface;
use App\Security\TemporaryLinkPolicy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ForgotPasswordRequestApplicationServiceTest extends TestCase
{
    private ForgotPasswordUserRepositoryInterface&MockObject $repository;
    private IdentityWritePersistenceInterface&MockObject $persistence;
    private TemporaryLinkPolicy $temporaryLinkPolicy;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ForgotPasswordUserRepositoryInterface::class);
        $this->persistence = $this->createMock(IdentityWritePersistenceInterface::class);
        $this->temporaryLinkPolicy = new TemporaryLinkPolicy();
    }

    public function testRequestNoActionForInvalidEmail(): void
    {
        $this->repository->expects(self::never())->method('findOneByEmail');
        $this->persistence->expects(self::never())->method('flush');

        $result = $this->service()->request('invalid-email', new \DateTimeImmutable());

        self::assertFalse($result->isTokenIssued());
    }

    public function testRequestNoActionWhenUserNotFound(): void
    {
        $this->repository->method('findOneByEmail')->willReturn(null);
        $this->persistence->expects(self::never())->method('flush');

        $result = $this->service()->request('unknown@example.com', new \DateTimeImmutable());

        self::assertFalse($result->isTokenIssued());
    }

    public function testRequestNoActionWhenCooldownActive(): void
    {
        $user = (new UserEntity())
            ->setEmail('test@example.com')
            ->setPassword('hash')
            ->setResetPasswordRequestedAt(new \DateTimeImmutable('-10 seconds'));

        $this->repository->method('findOneByEmail')->willReturn($user);
        $this->persistence->expects(self::never())->method('flush');

        $result = $this->service()->request('test@example.com', new \DateTimeImmutable());

        self::assertFalse($result->isTokenIssued());
    }

    public function testRequestIssuesTokenAndFlushes(): void
    {
        $user = (new UserEntity())
            ->setEmail('test@example.com')
            ->setPassword('hash')
            ->setResetPasswordRequestedAt(new \DateTimeImmutable('-2 hours'));

        $this->repository->method('findOneByEmail')->willReturn($user);
        $this->persistence->expects(self::once())->method('flush');

        $result = $this->service()->request('test@example.com', new \DateTimeImmutable());

        self::assertTrue($result->isTokenIssued());
        self::assertSame('test@example.com', $result->getEmail());
        self::assertNotNull($result->getPlainToken());
        self::assertNotNull($user->getResetPasswordTokenHash());
        self::assertNotNull($user->getResetPasswordExpiresAt());
        self::assertNotNull($user->getResetPasswordRequestedAt());
    }

    private function service(): ForgotPasswordRequestApplicationService
    {
        return new ForgotPasswordRequestApplicationService(
            $this->repository,
            $this->persistence,
            $this->temporaryLinkPolicy,
        );
    }
}
