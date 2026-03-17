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

use App\Catalog\Application\Import\ItemSourceCollisionReadRepository;
use App\Catalog\UI\Console\ReportItemSourceCollisionsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ReportItemSourceCollisionsCommandTest extends TestCase
{
    public function testTextOutputShowsCollisions(): void
    {
        $repository = $this->createMock(ItemSourceCollisionReadRepository::class);
        $repository
            ->method('findExternalRefCollisions')
            ->willReturn([
                [
                    'type' => 'BOOK',
                    'externalRef' => '003A2021',
                    'itemCount' => 2,
                    'providerCount' => 2,
                    'providers' => ['fandom', 'fallout_wiki'],
                    'sourceIds' => [1001, 1002],
                ],
            ]);

        $command = new ReportItemSourceCollisionsCommand($repository);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('003A2021', $tester->getDisplay());
        self::assertStringContainsString('1001, 1002', $tester->getDisplay());
    }

    public function testJsonOutputReturnsMachineReadablePayload(): void
    {
        $repository = $this->createMock(ItemSourceCollisionReadRepository::class);
        $repository
            ->method('findExternalRefCollisions')
            ->willReturn([]);

        $command = new ReportItemSourceCollisionsCommand($repository);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('fandom', $decoded['provider_a'] ?? null);
        self::assertSame('fallout_wiki', $decoded['provider_b'] ?? null);
        self::assertSame(0, $decoded['count'] ?? null);
    }
}
