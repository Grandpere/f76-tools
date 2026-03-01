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

use App\Catalog\Application\Minerva\MinervaRotationRefresher;
use App\Catalog\UI\Console\RefreshMinervaRotationCommand;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RefreshMinervaRotationCommandTest extends TestCase
{
    /** @var MinervaRotationRefresher&MockObject */
    private MinervaRotationRefresher $refreshService;

    protected function setUp(): void
    {
        $this->refreshService = $this->createMock(MinervaRotationRefresher::class);
    }

    public function testDryRunDisplaysCoverageResult(): void
    {
        $this->refreshService
            ->expects(self::once())
            ->method('refresh')
            ->with(
                self::isInstanceOf(DateTimeImmutable::class),
                self::isInstanceOf(DateTimeImmutable::class),
                true,
            )
            ->willReturn([
                'expectedWindows' => 12,
                'missingWindows' => 1,
                'covered' => false,
                'performed' => false,
                'deleted' => 0,
                'inserted' => 0,
                'skipped' => 0,
            ]);

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--from' => '2026-03-01',
            '--to' => '2026-06-30',
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Missing windows: 1', $tester->getDisplay());
        self::assertStringContainsString('Dry-run termine', $tester->getDisplay());
    }

    public function testInvalidRangeReturnsInvalidExitCode(): void
    {
        $this->refreshService
            ->expects(self::once())
            ->method('refresh')
            ->willThrowException(new InvalidArgumentException('invalid range'));

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--from' => '2026-06-30',
            '--to' => '2026-03-01',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Plage invalide', $tester->getDisplay());
    }

    private function createTester(): CommandTester
    {
        $command = new RefreshMinervaRotationCommand($this->refreshService);
        $command->setName('app:minerva:refresh-rotation');

        return new CommandTester($command);
    }
}
