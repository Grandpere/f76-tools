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

use App\Catalog\Application\NukeCode\NukeCodeReadApplicationService;
use App\Catalog\Application\NukeCode\NukeCodeReadRepository;
use App\Catalog\Application\NukeCode\NukeCodeResetCalculator;
use App\Catalog\UI\Console\WarmupNukeCodesCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class WarmupNukeCodesCommandTest extends TestCase
{
    public function testWarmupSucceeds(): void
    {
        $service = new NukeCodeReadApplicationService(
            new FixedNukeCodeRepository(),
            new NukeCodeResetCalculator(),
            new ArrayAdapter(),
            0,
            1800,
        );

        $tester = $this->createTester($service);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Warmup ok', $tester->getDisplay());
    }

    public function testWarmupFailsWhenRemoteUnavailableAndNoFallbackExists(): void
    {
        $service = new NukeCodeReadApplicationService(
            new FailingNukeCodeRepository(),
            new NukeCodeResetCalculator(),
            new ArrayAdapter(),
            0,
            1800,
        );

        $tester = $this->createTester($service);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Warmup nuke codes en echec', $tester->getDisplay());
    }

    private function createTester(NukeCodeReadApplicationService $service): CommandTester
    {
        $command = new WarmupNukeCodesCommand($service);
        $command->setName('app:nuke-codes:warmup');

        return new CommandTester($command);
    }
}

final class FixedNukeCodeRepository implements NukeCodeReadRepository
{
    public function fetchCurrent(): array
    {
        return [
            'alpha' => '59586541',
            'bravo' => '99725388',
            'charlie' => '00763938',
        ];
    }
}

final class FailingNukeCodeRepository implements NukeCodeReadRepository
{
    public function fetchCurrent(): array
    {
        throw new RuntimeException('upstream down');
    }
}
