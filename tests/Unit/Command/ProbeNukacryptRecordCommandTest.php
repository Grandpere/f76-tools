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

use App\Catalog\Application\Nukacrypt\NukacryptRecord;
use App\Catalog\Application\Nukacrypt\NukacryptRecordLookup;
use App\Catalog\UI\Console\ProbeNukacryptRecordCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ProbeNukacryptRecordCommandTest extends TestCase
{
    public function testTextOutputShowsResolvedRecords(): void
    {
        $lookup = new class implements NukacryptRecordLookup {
            public function search(string $searchTerm, array $signatures = ['BOOK']): array
            {
                TestCase::assertSame('Plan: Vault 96 Jumpsuit', $searchTerm);
                TestCase::assertSame(['BOOK'], $signatures);

                return [
                    new NukacryptRecord(
                        formId: '0031338F',
                        name: 'Plan: Vault 96 Jumpsuit',
                        editorId: 'recipe_Armor_VaultSuit96_Underarmor',
                        signature: 'BOOK',
                        description: null,
                        esmFileName: 'SeventySix.esm',
                        updatedAt: '2026-02-25T00:00:00+00:00',
                        hasErrors: false,
                        recordData: ['price' => 50],
                    ),
                ];
            }
        };

        $tester = new CommandTester(new ProbeNukacryptRecordCommand($lookup));
        $exitCode = $tester->execute([
            'query' => 'Plan: Vault 96 Jumpsuit',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Plan: Vault 96 Jumpsuit', $tester->getDisplay());
        self::assertStringContainsString('recipe_Armor_VaultSuit96_Underarmor', $tester->getDisplay());
    }

    public function testJsonOutputReturnsMachineReadablePayload(): void
    {
        $lookup = new class implements NukacryptRecordLookup {
            public function search(string $searchTerm, array $signatures = ['BOOK']): array
            {
                return [
                    new NukacryptRecord(
                        formId: '002B8BC4',
                        name: 'Recipe: Healing Salve (Toxic Valley)',
                        editorId: 'Recipe_Chems_HealingSalveToxicValley',
                        signature: 'BOOK',
                        description: null,
                        esmFileName: 'SeventySix.esm',
                        updatedAt: null,
                        hasErrors: false,
                        recordData: null,
                    ),
                ];
            }
        };

        $tester = new CommandTester(new ProbeNukacryptRecordCommand($lookup));
        $exitCode = $tester->execute([
            'query' => 'Recipe: Healing Salve (Toxic Valley)',
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        /** @var array<int, array<string, mixed>> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('002B8BC4', $decoded[0]['form_id'] ?? null);
        self::assertSame('Recipe: Healing Salve (Toxic Valley)', $decoded[0]['name'] ?? null);
        self::assertSame('Recipe_Chems_HealingSalveToxicValley', $decoded[0]['editor_id'] ?? null);
    }

    public function testFailureIsReportedWhenLookupThrows(): void
    {
        $lookup = new class implements NukacryptRecordLookup {
            public function search(string $searchTerm, array $signatures = ['BOOK']): array
            {
                throw new RuntimeException('boom');
            }
        };

        $tester = new CommandTester(new ProbeNukacryptRecordCommand($lookup));
        $exitCode = $tester->execute([
            'query' => 'Plan: Vault 96 Jumpsuit',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('boom', $tester->getDisplay());
    }
}
