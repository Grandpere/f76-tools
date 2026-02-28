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

use App\Identity\Application\Security\AuthAuditLogPurger;
use App\Support\Application\Admin\Audit\AdminAuditLogPurger;
use App\Support\UI\Console\RunAuditRetentionCommand;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RunAuditRetentionCommandTest extends TestCase
{
    /** @var AuthAuditLogPurger&MockObject */
    private AuthAuditLogPurger $authPurger;

    /** @var AdminAuditLogPurger&MockObject */
    private AdminAuditLogPurger $adminPurger;

    protected function setUp(): void
    {
        $this->authPurger = $this->createMock(AuthAuditLogPurger::class);
        $this->adminPurger = $this->createMock(AdminAuditLogPurger::class);
    }

    public function testDryRunCountsBothStoresWithoutDeleting(): void
    {
        $this->authPurger
            ->expects(self::once())
            ->method('countOlderThan')
            ->with(self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(5);
        $this->adminPurger
            ->expects(self::once())
            ->method('countOlderThan')
            ->with(self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(3);

        $this->authPurger->expects(self::never())->method('deleteOlderThan');
        $this->adminPurger->expects(self::never())->method('deleteOlderThan');

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--days' => '60',
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('auth=5, admin=3, total=8', $tester->getDisplay());
    }

    public function testRunDeletesBothStores(): void
    {
        $this->authPurger->expects(self::once())->method('deleteOlderThan')->willReturn(7);
        $this->adminPurger->expects(self::once())->method('deleteOlderThan')->willReturn(2);
        $this->authPurger->expects(self::never())->method('countOlderThan');
        $this->adminPurger->expects(self::never())->method('countOlderThan');

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--days' => '90',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('auth=7, admin=2, total=9', $tester->getDisplay());
    }

    public function testFailsWhenDaysOptionIsInvalid(): void
    {
        $this->authPurger->expects(self::never())->method('countOlderThan');
        $this->adminPurger->expects(self::never())->method('countOlderThan');
        $this->authPurger->expects(self::never())->method('deleteOlderThan');
        $this->adminPurger->expects(self::never())->method('deleteOlderThan');

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--days' => 'invalid',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Option --days invalide', $tester->getDisplay());
    }

    private function createTester(): CommandTester
    {
        $command = new RunAuditRetentionCommand($this->authPurger, $this->adminPurger);
        $command->setName('app:audit:retention:run');

        return new CommandTester($command);
    }
}
