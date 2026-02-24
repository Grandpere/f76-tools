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

namespace App\Tests\Unit\Command;

use App\Command\CreateUserCommand;
use App\Contract\UserByEmailFinderInterface;
use App\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateUserCommandTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserByEmailFinderInterface&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserByEmailFinderInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
    }

    public function testCreatesNewUser(): void
    {
        $this->userRepository
            ->expects(self::once())
            ->method('findOneByEmail')
            ->with('new.user@example.com')
            ->willReturn(null);

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(UserEntity::class));
        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with(self::isInstanceOf(UserEntity::class), 'password123')
            ->willReturn('hashed-password');

        $commandTester = $this->createCommandTester();
        $exitCode = $commandTester->execute([
            'email' => 'new.user@example.com',
            '--password' => 'password123',
            '--role' => ['ROLE_ADMIN'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Utilisateur cree: new.user@example.com', $commandTester->getDisplay());
    }

    public function testFailsIfUserAlreadyExistsWithoutUpdateOption(): void
    {
        $existing = (new UserEntity())
            ->setEmail('existing@example.com')
            ->setPassword('old-hash')
            ->setRoles(['ROLE_USER']);

        $this->userRepository
            ->expects(self::once())
            ->method('findOneByEmail')
            ->with('existing@example.com')
            ->willReturn($existing);

        $this->entityManager
            ->expects(self::never())
            ->method('persist');
        $this->entityManager
            ->expects(self::never())
            ->method('flush');
        $this->passwordHasher
            ->expects(self::never())
            ->method('hashPassword');

        $commandTester = $this->createCommandTester();
        $exitCode = $commandTester->execute([
            'email' => 'existing@example.com',
            '--password' => 'password123',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Utilise', $commandTester->getDisplay());
        self::assertStringContainsString('--update-password', $commandTester->getDisplay());
    }

    public function testUpdatesPasswordForExistingUserWhenOptionIsEnabled(): void
    {
        $existing = (new UserEntity())
            ->setEmail('existing@example.com')
            ->setPassword('old-hash')
            ->setRoles(['ROLE_USER']);

        $this->userRepository
            ->expects(self::once())
            ->method('findOneByEmail')
            ->with('existing@example.com')
            ->willReturn($existing);

        $this->entityManager
            ->expects(self::never())
            ->method('persist');
        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with($existing, 'password123')
            ->willReturn('new-hash');

        $commandTester = $this->createCommandTester();
        $exitCode = $commandTester->execute([
            'email' => 'existing@example.com',
            '--password' => 'password123',
            '--update-password' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('new-hash', $existing->getPassword());
        self::assertStringContainsString('Mot de passe mis a jour pour: existing@example.com', $commandTester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        $command = new CreateUserCommand(
            $this->entityManager,
            $this->userRepository,
            $this->passwordHasher,
        );
        $command->setName('app:user:create');

        return new CommandTester($command);
    }
}
