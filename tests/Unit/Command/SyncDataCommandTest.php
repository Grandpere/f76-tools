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
use Symfony\Component\Console\Application;
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
        $fandomCommand = new TestDelegatedSyncCommand('app:data:sync:fandom', Command::SUCCESS);

        $application = new Application();
        $application->addCommand($syncCommand);
        $application->addCommand($fandomCommand);

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fandom',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, $fandomCommand->calls);
    }

    public function testOnlyFandomForwardsSelectedPages(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $fandomCommand = new TestDelegatedSyncCommand('app:data:sync:fandom', Command::SUCCESS);

        $application = new Application();
        $application->addCommand($syncCommand);
        $application->addCommand($fandomCommand);

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fandom',
            '--fandom-page' => ['Fallout_76_plans/Weapons'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(['Fallout_76_plans/Weapons'], $fandomCommand->lastPages);
    }

    public function testOnlyFalloutWikiDelegatesToFalloutWikiCommandAndSucceeds(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $falloutWikiCommand = new TestDelegatedSyncCommand('app:data:sync:fallout-wiki', Command::SUCCESS);

        $application = new Application();
        $application->addCommand($syncCommand);
        $application->addCommand($falloutWikiCommand);

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fallout-wiki',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, $falloutWikiCommand->calls);
    }

    public function testOnlyFalloutWikiForwardsSelectedPages(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $falloutWikiCommand = new TestDelegatedSyncCommand('app:data:sync:fallout-wiki', Command::SUCCESS);

        $application = new Application();
        $application->addCommand($syncCommand);
        $application->addCommand($falloutWikiCommand);

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fallout-wiki',
            '--fallout-wiki-page' => ['Fallout_76_Weapon_Plans'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(['Fallout_76_Weapon_Plans'], $falloutWikiCommand->lastPages);
    }

    public function testOnlyFandomJsonFormatReturnsMachineReadablePayload(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $fandomCommand = new TestDelegatedSyncCommand('app:data:sync:fandom', Command::SUCCESS);

        $application = new Application();
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

    public function testOnlyFalloutWikiJsonFormatReturnsMachineReadablePayload(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $falloutWikiCommand = new TestDelegatedSyncCommand('app:data:sync:fallout-wiki', Command::SUCCESS);

        $application = new Application();
        $application->addCommand($syncCommand);
        $application->addCommand($falloutWikiCommand);

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fallout-wiki',
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('fallout-wiki', $decoded['scope'] ?? null);
        self::assertSame('ok', $decoded['status'] ?? null);
        self::assertSame('ok', $decoded['fallout_wiki_status'] ?? null);
    }

    public function testOnlyFandomFailsWhenDelegatedCommandFails(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $syncCommand = new SyncDataCommand($kernel);
        $fandomCommand = new TestDelegatedSyncCommand('app:data:sync:fandom', Command::FAILURE);

        $application = new Application();
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
        $application = new Application();
        $application->addCommand($syncCommand);
        $application->addCommand(new TestDelegatedSyncCommand('app:data:sync:fandom', Command::SUCCESS));

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'fandom',
            '--format' => 'yaml',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    public function testOnlyNukaknightsJsonFormatIncludesIndexAndSummary(): void
    {
        $projectDir = sys_get_temp_dir().'/f76-sync-'.uniqid('', true);
        mkdir($projectDir, 0o777, true);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $syncCommand = new TestableSyncDataCommand($kernel);
        $application = new Application();
        $application->addCommand($syncCommand);

        $tester = new CommandTester($syncCommand);
        $exitCode = $tester->execute([
            '--only' => 'nukaknights',
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        /** @var array<string, int> $updatedBySource */
        $updatedBySource = is_array($decoded['updated_by_source'] ?? null) ? $decoded['updated_by_source'] : [];
        self::assertSame(28, $updatedBySource['nukaknights'] ?? null);
        self::assertSame('data/sources/nukaknights/index.json', $decoded['nukaknights_index'] ?? null);
        self::assertCount(28, $syncCommand->syncedTargets);
        self::assertArrayHasKey($projectDir.'/data/sources/nukaknights/index.json', $syncCommand->writtenJson);
        /** @var array<string, mixed> $index */
        $index = $syncCommand->writtenJson[$projectDir.'/data/sources/nukaknights/index.json'];
        self::assertSame('nukaknights.com', $index['source'] ?? null);
        self::assertSame(2, $index['datasets_count'] ?? null);
        self::assertSame(28, $index['files_total'] ?? null);
        /** @var list<array<string, mixed>> $datasets */
        $datasets = is_array($index['datasets'] ?? null) ? $index['datasets'] : [];
        self::assertCount(2, $datasets);
    }
}

final class TestDelegatedSyncCommand extends Command
{
    public int $calls = 0;
    /** @var list<string> */
    public array $lastPages = [];

    public function __construct(string $name, private readonly int $code)
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ++$this->calls;
        $pages = $input->getOption('page');
        if (is_array($pages)) {
            $this->lastPages = array_values(array_map(
                static fn (string|int|float|bool $page): string => trim((string) $page),
                array_filter($pages, static fn (mixed $page): bool => is_scalar($page) && '' !== trim((string) $page)),
            ));
        }

        return $this->code;
    }

    protected function configure(): void
    {
        $this->addOption('page', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED | \Symfony\Component\Console\Input\InputOption::VALUE_IS_ARRAY);
    }
}

final class TestableSyncDataCommand extends SyncDataCommand
{
    /** @var list<string> */
    public array $syncedTargets = [];

    /** @var array<string, array<string, mixed>> */
    public array $writtenJson = [];

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);
        $this->setName('app:data:sync');
    }

    protected function syncFile(\Symfony\Contracts\HttpClient\HttpClientInterface $httpClient, string $url, string $target, array &$errors): bool
    {
        $this->syncedTargets[] = $target;

        return true;
    }

    protected function writeJson(string $path, array $payload): void
    {
        $this->writtenJson[$path] = $payload;
    }
}
