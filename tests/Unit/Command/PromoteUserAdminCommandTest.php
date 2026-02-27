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

use App\Identity\Application\User\UserByEmailFinder;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\UI\Console\PromoteUserAdminCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PromoteUserAdminCommandTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserByEmailFinder&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserByEmailFinder::class);
    }

    public function testFailsWhenUserDoesNotExist(): void
    {
        $this->userRepository
            ->expects(self::once())
            ->method('findOneByEmail')
            ->with('missing@example.com')
            ->willReturn(null);

        $this->entityManager->expects(self::never())->method('flush');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['email' => 'missing@example.com']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Utilisateur introuvable', $tester->getDisplay());
    }

    public function testPromotesExistingUserToAdmin(): void
    {
        $user = new UserEntity()
            ->setEmail('member@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);

        $this->userRepository
            ->expects(self::once())
            ->method('findOneByEmail')
            ->with('member@example.com')
            ->willReturn($user);

        $this->entityManager->expects(self::once())->method('flush');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['email' => 'member@example.com']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertTrue(in_array('ROLE_ADMIN', $user->getRoles(), true));
        self::assertStringContainsString('Utilisateur promu admin', $tester->getDisplay());
    }

    public function testReturnsSuccessWhenUserAlreadyAdmin(): void
    {
        $user = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_ADMIN']);

        $this->userRepository
            ->expects(self::once())
            ->method('findOneByEmail')
            ->with('admin@example.com')
            ->willReturn($user);

        $this->entityManager->expects(self::never())->method('flush');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['email' => 'admin@example.com']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('deja admin', $tester->getDisplay());
    }

    private function createTester(): CommandTester
    {
        $command = new PromoteUserAdminCommand($this->entityManager, $this->userRepository);
        $command->setName('app:user:promote-admin');

        return new CommandTester($command);
    }
}
