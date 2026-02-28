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
use App\Identity\UI\Console\PurgeAuthAuditLogsCommand;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeAuthAuditLogsCommandTest extends TestCase
{
    /** @var AuthAuditLogPurger&MockObject */
    private AuthAuditLogPurger $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuthAuditLogPurger::class);
    }

    public function testDryRunCountsWithoutDeleting(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('countOlderThan')
            ->with(self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(21);

        $this->repository
            ->expects(self::never())
            ->method('deleteOlderThan');

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--days' => '45',
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Dry-run: 21', $tester->getDisplay());
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
            ->willReturn(9);

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--days' => '120',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('9 log(s) auth supprimes', $tester->getDisplay());
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
        $command = new PurgeAuthAuditLogsCommand($this->repository);
        $command->setName('app:auth:audit:purge');

        return new CommandTester($command);
    }
}
