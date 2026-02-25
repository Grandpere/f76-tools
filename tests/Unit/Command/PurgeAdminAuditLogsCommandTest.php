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

use App\Command\PurgeAdminAuditLogsCommand;
use App\Contract\AdminAuditLogPurgerInterface;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeAdminAuditLogsCommandTest extends TestCase
{
    /** @var AdminAuditLogPurgerInterface&MockObject */
    private AdminAuditLogPurgerInterface $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AdminAuditLogPurgerInterface::class);
    }

    public function testDryRunCountsWithoutDeleting(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('countOlderThan')
            ->with(self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(12);

        $this->repository
            ->expects(self::never())
            ->method('deleteOlderThan');

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--days' => '30',
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Dry-run: 12', $tester->getDisplay());
    }

    public function testDeleteOlderThanWhenNotDryRun(): void
    {
        $this->repository
            ->expects(self::never())
            ->method('countOlderThan');

        $this->repository
            ->expects(self::once())
            ->method('deleteOlderThan')
            ->with(self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(7);

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--days' => '120',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('7 log(s) admin supprimes', $tester->getDisplay());
    }

    public function testFailsWhenDaysOptionIsInvalid(): void
    {
        $this->repository->expects(self::never())->method('countOlderThan');
        $this->repository->expects(self::never())->method('deleteOlderThan');

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--days' => 'abc',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Option --days invalide', $tester->getDisplay());
    }

    private function createTester(): CommandTester
    {
        $command = new PurgeAdminAuditLogsCommand($this->repository);
        $command->setName('app:admin:audit:purge');

        return new CommandTester($command);
    }
}
