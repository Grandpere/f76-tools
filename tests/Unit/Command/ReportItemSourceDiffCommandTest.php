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

use App\Catalog\Application\Import\ItemSourceComparisonReadRepository;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Catalog\UI\Console\ReportItemSourceDiffCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ReportItemSourceDiffCommandTest extends TestCase
{
    public function testTextOutputShowsDiffKeys(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(3809313)
            ->setNameKey('item.book.3809313.name');
        $item->upsertExternalSource('fandom', '003A2021', 'https://fallout.fandom.com/wiki/Recipe:Delbert', [
            'name_en' => "Recipe: Delbert's Company Tea",
            'vendors' => false,
            'obtained' => 'Quest',
        ]);
        $item->upsertExternalSource('fallout_wiki', '003A2021', 'https://fallout.wiki/wiki/Recipe:Delbert', [
            'name_en' => "Recipe: Delbert's Company Tea",
            'vendors' => true,
            'obtained' => 'World Object',
        ]);

        $repository = $this->createMock(ItemSourceComparisonReadRepository::class);
        $repository
            ->method('findItemsWithProviders')
            ->willReturn([$item]);

        $command = new ReportItemSourceDiffCommand($repository);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('vendors', $tester->getDisplay());
        self::assertStringContainsString('obtained', $tester->getDisplay());
    }

    public function testJsonOutputReturnsMachineReadableRows(): void
    {
        $item = new ItemEntity()
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(3809313)
            ->setNameKey('item.book.3809313.name');
        $item->upsertExternalSource('fandom', '003A2021', 'https://fallout.fandom.com/wiki/Recipe:Delbert', [
            'name_en' => "Recipe: Delbert's Company Tea",
        ]);
        $item->upsertExternalSource('fallout_wiki', '003A2021', 'https://fallout.wiki/wiki/Recipe:Delbert', [
            'name_en' => "Recipe: Delbert's Company Tea",
        ]);

        $repository = $this->createMock(ItemSourceComparisonReadRepository::class);
        $repository
            ->method('findItemsWithProviders')
            ->willReturn([$item]);

        $command = new ReportItemSourceDiffCommand($repository);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--format' => 'json',
            '--show-equal' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('fandom', $decoded['provider_a'] ?? null);
        self::assertSame('fallout_wiki', $decoded['provider_b'] ?? null);
        self::assertSame(1, $decoded['items_scanned'] ?? null);
        self::assertIsArray($decoded['rows'] ?? null);
        self::assertCount(1, $decoded['rows']);
    }
}
