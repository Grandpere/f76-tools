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
use App\Catalog\Application\Import\ItemSourceMergePolicy;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Catalog\UI\Console\ReportItemSourceMergeSummaryCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ReportItemSourceMergeSummaryCommandTest extends TestCase
{
    public function testTextOutputShowsFieldSummary(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(2161)
            ->setNameKey('item.book.2161.name');
        $item->upsertExternalSource('fandom', '00000871', null, [
            'name_en' => 'Plan: Assault rifle fierce receiver',
            'containers' => true,
        ]);
        $item->upsertExternalSource('fallout_wiki', '00000871', null, [
            'name_en' => 'Plan: Assault Rifle Fierce Receiver',
            'unlocks' => ['text' => 'Fierce receiver'],
        ]);

        $repository = $this->createMock(ItemSourceComparisonReadRepository::class);
        $repository
            ->method('findItemsWithProviders')
            ->willReturn([$item]);

        $command = new ReportItemSourceMergeSummaryCommand($repository, new ItemSourceMergePolicy());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('containers', $tester->getDisplay());
        self::assertStringContainsString('unlocks', $tester->getDisplay());
    }

    public function testJsonOutputReturnsSummaryByField(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(42)
            ->setNameKey('item.book.42.name');
        $item->upsertExternalSource('fandom', '42', null, [
            'name_en' => 'Recipe: Refreshing beverage',
        ]);
        $item->upsertExternalSource('fallout_wiki', '42', null, [
            'name_en' => "Recipe: Delbert's Company Tea",
        ]);

        $repository = $this->createMock(ItemSourceComparisonReadRepository::class);
        $repository
            ->method('findItemsWithProviders')
            ->willReturn([$item]);

        $command = new ReportItemSourceMergeSummaryCommand($repository, new ItemSourceMergePolicy());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('fandom', $decoded['provider_a'] ?? null);
        self::assertSame('fallout_wiki', $decoded['provider_b'] ?? null);
        self::assertSame(1, $decoded['items_with_merge'] ?? null);
        self::assertIsArray($decoded['fields'] ?? null);
        /** @var array<string, mixed> $fields */
        $fields = $decoded['fields'];
        self::assertArrayHasKey('name_en', $fields);
        /** @var array{conflicts:mixed} $nameField */
        $nameField = $fields['name_en'];
        self::assertSame(1, $nameField['conflicts']);
    }

    public function testJsonOutputCountsGenericLabelRetainedSeparately(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(2853828)
            ->setNameKey('item.book.2853828.name');
        $item->upsertExternalSource('fandom', '002B8BC4', 'https://fallout.fandom.com/wiki/Recipe:_Healing_salve_(Toxic_Valley)', [
            'name_en' => 'Recipe: Healing salve (Toxic Valley)',
        ]);
        $item->upsertExternalSource('fallout_wiki', '002B8BC4', 'https://fallout.wiki/wiki/Recipe:_Healing_Salve_(Toxic_Valley)', [
            'name_en' => 'Recipe: Healing Salve',
        ]);

        $repository = $this->createMock(ItemSourceComparisonReadRepository::class);
        $repository
            ->method('findItemsWithProviders')
            ->willReturn([$item]);

        $command = new ReportItemSourceMergeSummaryCommand($repository, new ItemSourceMergePolicy());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $decoded['items_with_generic_labels'] ?? null);
        self::assertSame(0, $decoded['items_with_material_conflicts'] ?? null);
        /** @var array<string, mixed> $fields */
        $fields = $decoded['fields'];
        /** @var array<string, mixed> $nameField */
        $nameField = $fields['name_en'];
        self::assertSame(1, $nameField['generic_label_retained'] ?? null);
        self::assertSame(0, $nameField['conflicts'] ?? null);
    }
}
