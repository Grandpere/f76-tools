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
use App\Catalog\Application\Nukacrypt\NukacryptRecord;
use App\Catalog\Application\Nukacrypt\NukacryptRecordLookup;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Catalog\UI\Console\ReportItemSourceArbitrationCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ReportItemSourceArbitrationCommandTest extends TestCase
{
    public function testJsonOutputShowsArbitrationVerdict(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(2835108)
            ->setNameKey('item.book.2835108.name');
        $item->upsertExternalSource('fandom', '002B42A4', null, [
            'name_en' => 'Plan: Bladed Commie Whacker',
        ]);
        $item->upsertExternalSource('fallout_wiki', '002B42A4', null, [
            'name_en' => 'Plan: Garden Trowel Knife',
        ]);

        $repository = $this->createMock(ItemSourceComparisonReadRepository::class);
        $repository
            ->method('findItemsWithProviders')
            ->willReturn([$item]);

        $lookup = new class implements NukacryptRecordLookup {
            public function search(string $searchTerm, array $signatures = ['BOOK']): array
            {
                TestCase::assertSame([], $signatures);

                return match ($searchTerm) {
                    'Plan: Bladed Commie Whacker' => [
                        new NukacryptRecord(
                            formId: '002B42A4',
                            name: 'Plan: Bladed Commie Whacker',
                            editorId: 'recipe_DLC04_mod_melee_DLC04_CommieWhacker_BladesLarge',
                            signature: 'BOOK',
                            description: null,
                            esmFileName: null,
                            updatedAt: null,
                            hasErrors: false,
                            recordData: null,
                        ),
                    ],
                    'Plan: Garden Trowel Knife' => [
                        new NukacryptRecord(
                            formId: '007D6606',
                            name: 'Plan: Garden Trowel Knife',
                            editorId: 'SSE_Recipe_mod_CombatKnife_Material_Paint_GardenTrowel',
                            signature: 'BOOK',
                            description: null,
                            esmFileName: null,
                            updatedAt: null,
                            hasErrors: false,
                            recordData: null,
                        ),
                    ],
                    default => [],
                };
            }

            public function searchByEditorId(string $editorId, array $signatures = ['BOOK']): array
            {
                return [];
            }
        };

        $tester = new CommandTester(new ReportItemSourceArbitrationCommand($repository, $lookup));
        $exitCode = $tester->execute([
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $decoded['items_with_name_conflicts'] ?? null);
        self::assertSame(1, $decoded['resolved_items'] ?? null);
        self::assertSame(0, $decoded['unresolved_items'] ?? null);
        self::assertIsArray($decoded['rows'] ?? null);
        self::assertCount(1, $decoded['rows']);
        /** @var array<string, mixed> $row */
        $row = $decoded['rows'][0];
        self::assertSame('confirmed_provider_a', $row['verdict'] ?? null);
        self::assertSame('fandom', $row['matchProvider'] ?? null);
        self::assertSame('002B42A4', $row['expectedFormId'] ?? null);
    }

    public function testTextOutputShowsLookupErrors(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(3224464)
            ->setNameKey('item.book.3224464.name');
        $item->upsertExternalSource('fandom', '00313390', null, [
            'name_en' => 'Plan: Vault 63 Jumpsuit',
        ]);
        $item->upsertExternalSource('fallout_wiki', '00313390', null, [
            'name_en' => 'Plan: Vault 96 Jumpsuit',
        ]);

        $repository = $this->createMock(ItemSourceComparisonReadRepository::class);
        $repository
            ->method('findItemsWithProviders')
            ->willReturn([$item]);

        $lookup = new class implements NukacryptRecordLookup {
            public function search(string $searchTerm, array $signatures = ['BOOK']): array
            {
                throw new RuntimeException('Upstream body empty');
            }

            public function searchByEditorId(string $editorId, array $signatures = ['BOOK']): array
            {
                return [];
            }
        };

        $tester = new CommandTester(new ReportItemSourceArbitrationCommand($repository, $lookup));
        $exitCode = $tester->execute([
            '--signature' => ['BOOK'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('lookup_error', $tester->getDisplay());
        self::assertStringContainsString('Plan: Vault 63 Jumpsuit', $tester->getDisplay());
    }
}
