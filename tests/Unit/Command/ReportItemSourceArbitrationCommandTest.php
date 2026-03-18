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

    public function testJsonOutputPrefersSpecificVariantWhenGenericCandidateMatchesMultipleForms(): void
    {
        $item = new ItemEntity();
        $item
            ->setType(ItemTypeEnum::BOOK)
            ->setSourceId(2853828)
            ->setNameKey('item.book.2853828.name');
        $item->upsertExternalSource('fandom', '002B8BC4', null, [
            'name_en' => 'Recipe: Healing Salve (Toxic Valley)',
            'wiki_url' => 'https://fallout.fandom.com/wiki/Recipe:_Healing_salve_(Toxic_Valley)',
            'source_slug' => 'Recipe:_Healing_salve_(Toxic_Valley)',
        ]);
        $item->upsertExternalSource('fallout_wiki', '002B8BC4', null, [
            'name_en' => 'Recipe: Healing Salve',
            'wiki_url' => 'https://fallout.wiki/wiki/Recipe:_Healing_Salve_(Toxic_Valley)',
            'source_slug' => 'recipe-healing-salve-toxic-valley',
        ]);

        $repository = $this->createMock(ItemSourceComparisonReadRepository::class);
        $repository
            ->method('findItemsWithProviders')
            ->willReturn([$item]);

        $lookup = new class implements NukacryptRecordLookup {
            public function search(string $searchTerm, array $signatures = ['BOOK']): array
            {
                return match ($searchTerm) {
                    'Recipe: Healing Salve (Toxic Valley)' => [
                        new NukacryptRecord(
                            formId: '002B8BC4',
                            name: 'Recipe: Healing Salve (Toxic Valley)',
                            editorId: 'Recipe_Chems_HealingSalveToxicValley',
                            signature: 'BOOK',
                            description: null,
                            esmFileName: null,
                            updatedAt: null,
                            hasErrors: false,
                            recordData: null,
                        ),
                    ],
                    'Recipe: Healing Salve' => [
                        new NukacryptRecord(
                            formId: '002B8BC0',
                            name: 'Recipe: Healing Salve (Cranberry Bog)',
                            editorId: 'Recipe_Chems_HealingSalveCranberryBog',
                            signature: 'BOOK',
                            description: null,
                            esmFileName: null,
                            updatedAt: null,
                            hasErrors: false,
                            recordData: null,
                        ),
                        new NukacryptRecord(
                            formId: '002B8BC4',
                            name: 'Recipe: Healing Salve (Toxic Valley)',
                            editorId: 'Recipe_Chems_HealingSalveToxicValley',
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
        self::assertIsArray($decoded['rows'] ?? null);
        self::assertSame(1, $decoded['generic_label_items'] ?? null);
        self::assertSame(0, $decoded['material_conflict_items'] ?? null);
        /** @var array<string, mixed> $row */
        $row = $decoded['rows'][0];
        self::assertSame('provider_b_generic_label_confirmed', $row['verdict'] ?? null);
        self::assertSame('fallout_wiki', $row['matchProvider'] ?? null);
        self::assertSame(1, $row['matchingRecordsATotal'] ?? null);
        self::assertSame(1, $row['matchingRecordsBTotal'] ?? null);
        self::assertSame(2, $row['recordsBTotal'] ?? null);
        self::assertTrue($row['sourceUrlBIsSpecific'] ?? false);
        self::assertIsArray($row['recordsB'] ?? null);
        /** @var list<array<string, mixed>> $recordsB */
        $recordsB = $row['recordsB'];
        self::assertCount(1, $row['recordsB']);
        self::assertSame('002B8BC4', $recordsB[0]['form_id'] ?? null);
    }
}
