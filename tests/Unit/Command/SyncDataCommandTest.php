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

use App\Catalog\UI\Console\SyncDataCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class SyncDataCommandTest extends TestCase
{
    public function testOnlyFandomDelegatesToFandomCommandAndSucceeds(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $fandomCommand = new TestFandomCommand(Command::SUCCESS);

        $application = new \Symfony\Component\Console\Application();
        $application->addCommand($syncCommand);
        $application->addCommand($fandomCommand);

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fandom',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, $fandomCommand->calls);
    }

    public function testOnlyFandomJsonFormatReturnsMachineReadablePayload(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $fandomCommand = new TestFandomCommand(Command::SUCCESS);

        $application = new \Symfony\Component\Console\Application();
        $application->addCommand($syncCommand);
        $application->addCommand($fandomCommand);

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fandom',
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('fandom', $decoded['scope'] ?? null);
        self::assertSame('ok', $decoded['status'] ?? null);
    }

    public function testOnlyFandomFailsWhenDelegatedCommandFails(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $fandomCommand = new TestFandomCommand(Command::FAILURE);

        $application = new \Symfony\Component\Console\Application();
        $application->addCommand($syncCommand);
        $application->addCommand($fandomCommand);

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fandom',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(1, $fandomCommand->calls);
    }

    public function testInvalidFormatReturnsInvalidCode(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $application = new \Symfony\Component\Console\Application();
        $application->addCommand($syncCommand);
        $application->addCommand(new TestFandomCommand(Command::SUCCESS));

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fandom',
            '--format' => 'yaml',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }
}

final class TestFandomCommand extends Command
{
    public int $calls = 0;

    public function __construct(private readonly int $code)
    {
        parent::__construct('app:data:sync:fandom');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ++$this->calls;

        return $this->code;
    }
}
