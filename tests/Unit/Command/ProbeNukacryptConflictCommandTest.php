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
use App\Catalog\UI\Console\ProbeNukacryptConflictCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ProbeNukacryptConflictCommandTest extends TestCase
{
    public function testJsonOutputShowsWhichCandidateMatchesExpectedFormId(): void
    {
        $lookup = new class implements NukacryptRecordLookup {
            public function search(string $searchTerm, array $signatures = ['BOOK']): array
            {
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

        $tester = new CommandTester(new ProbeNukacryptConflictCommand($lookup));
        $exitCode = $tester->execute([
            '--expected-form-id' => '002B42A4',
            '--candidate' => [
                'Plan: Bladed Commie Whacker',
                'Plan: Garden Trowel Knife',
            ],
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        /** @var list<array<string, mixed>> $results */
        $results = is_array($decoded['results'] ?? null) ? $decoded['results'] : [];
        self::assertSame('002B42A4', $decoded['expected_form_id'] ?? null);
        self::assertTrue($results[0]['matchesExpected'] ?? false);
        self::assertFalse($results[1]['matchesExpected'] ?? true);
    }

    public function testTextOutputIncludesEditorIdProbe(): void
    {
        $lookup = new class implements NukacryptRecordLookup {
            public function search(string $searchTerm, array $signatures = ['BOOK']): array
            {
                return [];
            }

            public function searchByEditorId(string $editorId, array $signatures = ['BOOK']): array
            {
                TestCase::assertSame('recipe_Armor_VaultSuit96_Underarmor', $editorId);

                return [
                    new NukacryptRecord(
                        formId: '0031338F',
                        name: 'Plan: Vault 96 Jumpsuit',
                        editorId: 'recipe_Armor_VaultSuit96_Underarmor',
                        signature: 'BOOK',
                        description: null,
                        esmFileName: null,
                        updatedAt: null,
                        hasErrors: false,
                        recordData: null,
                    ),
                ];
            }
        };

        $tester = new CommandTester(new ProbeNukacryptConflictCommand($lookup));
        $exitCode = $tester->execute([
            '--expected-form-id' => '0031338F',
            '--editor-id' => 'recipe_Armor_VaultSuit96_Underarmor',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('editor_id', $tester->getDisplay());
        self::assertStringContainsString('0031338F', $tester->getDisplay());
    }

    public function testCommandReportsLookupErrorPerCandidateInsteadOfFailingWholeProbe(): void
    {
        $lookup = new class implements NukacryptRecordLookup {
            public function search(string $searchTerm, array $signatures = ['BOOK']): array
            {
                throw new RuntimeException('boom');
            }

            public function searchByEditorId(string $editorId, array $signatures = ['BOOK']): array
            {
                throw new RuntimeException('boom');
            }
        };

        $tester = new CommandTester(new ProbeNukacryptConflictCommand($lookup));
        $exitCode = $tester->execute([
            '--expected-form-id' => '002B42A4',
            '--candidate' => ['Plan: Bladed Commie Whacker'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('boom', $tester->getDisplay());
    }
}
