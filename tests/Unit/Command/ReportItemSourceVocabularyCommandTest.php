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

use App\Catalog\Application\Import\ItemImportExternalMetadataEnricher;
use App\Catalog\Infrastructure\Import\FilesystemItemImportSourceReader;
use App\Catalog\UI\Console\ReportItemSourceVocabularyCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class ReportItemSourceVocabularyCommandTest extends TestCase
{
    public function testJsonOutputAggregatesObservedValuesBySection(): void
    {
        $projectDir = $this->createFixtureProjectDir();

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $command = new ReportItemSourceVocabularyCommand(new FilesystemItemImportSourceReader(), new ItemImportExternalMetadataEnricher(), $kernel);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNull($decoded['provider'] ?? null);
        self::assertNull($decoded['field'] ?? null);
        self::assertFalse($decoded['only_unmapped'] ?? true);
        self::assertIsArray($decoded['sections'] ?? null);
        self::assertCount(3, $decoded['sections']);

        /** @var list<array<string, mixed>> $sections */
        $sections = $decoded['sections'];
        $availability = $this->findSection($sections, 'fandom', 'availability');
        /** @var list<array<string, mixed>> $availabilityRows */
        $availabilityRows = is_array($availability['rows'] ?? null) ? $availability['rows'] : [];
        self::assertSame(1, $availability['files_scanned'] ?? null);
        self::assertSame(2, $availability['rows_scanned'] ?? null);
        self::assertSame([
            'kind' => 'flag',
            'value' => 'enemies',
            'count' => 2,
            'file_count' => 1,
            'truthy_count' => 1,
            'falsy_count' => 1,
            'mapped_fields' => ['enemies'],
        ], $this->findRow($availabilityRows, 'flag', 'enemies'));

        $obtained = $this->findSection($sections, 'fallout_wiki', 'obtained');
        /** @var list<array<string, mixed>> $obtainedRows */
        $obtainedRows = is_array($obtained['rows'] ?? null) ? $obtained['rows'] : [];
        self::assertSame([
            'kind' => 'icon',
            'value' => 'Enemy Drop',
            'count' => 1,
            'file_count' => 1,
            'truthy_count' => null,
            'falsy_count' => null,
            'mapped_fields' => ['enemies'],
        ], $this->findRow($obtainedRows, 'icon', 'Enemy Drop'));
        self::assertSame([
            'kind' => 'text',
            'value' => 'Project Paradise',
            'count' => 1,
            'file_count' => 1,
            'truthy_count' => null,
            'falsy_count' => null,
            'mapped_fields' => [],
        ], $this->findRow($obtainedRows, 'text', 'Project Paradise'));

        $type = $this->findSection($sections, 'fallout_wiki', 'type');
        /** @var list<array<string, mixed>> $typeRows */
        $typeRows = is_array($type['rows'] ?? null) ? $type['rows'] : [];
        self::assertSame([
            'kind' => 'value',
            'value' => 'caps',
            'count' => 1,
            'file_count' => 1,
            'truthy_count' => null,
            'falsy_count' => null,
            'mapped_fields' => ['purchase_currency', 'vendors'],
        ], $this->findRow($typeRows, 'value', 'caps'));
    }

    public function testProviderAndFieldFiltersLimitOutput(): void
    {
        $projectDir = $this->createFixtureProjectDir();

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $command = new ReportItemSourceVocabularyCommand(new FilesystemItemImportSourceReader(), new ItemImportExternalMetadataEnricher(), $kernel);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--provider' => 'fallout_wiki',
            '--field' => 'obtained',
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('fallout_wiki', $decoded['provider'] ?? null);
        self::assertSame('obtained', $decoded['field'] ?? null);
        self::assertFalse($decoded['only_unmapped'] ?? true);
        self::assertIsArray($decoded['sections'] ?? null);
        self::assertCount(1, $decoded['sections']);
        /** @var list<array<string, mixed>> $sections */
        $sections = $decoded['sections'];
        self::assertSame('fallout_wiki', $sections[0]['provider'] ?? null);
        self::assertSame('obtained', $sections[0]['field'] ?? null);
    }

    public function testInvalidFieldCombinationReturnsInvalidCode(): void
    {
        $projectDir = $this->createFixtureProjectDir();

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $command = new ReportItemSourceVocabularyCommand(new FilesystemItemImportSourceReader(), new ItemImportExternalMetadataEnricher(), $kernel);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--provider' => 'fandom',
            '--field' => 'obtained',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    public function testOnlyUnmappedFilterKeepsOnlyResidualRows(): void
    {
        $projectDir = $this->createFixtureProjectDir();

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $command = new ReportItemSourceVocabularyCommand(new FilesystemItemImportSourceReader(), new ItemImportExternalMetadataEnricher(), $kernel);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--provider' => 'fallout_wiki',
            '--field' => 'obtained',
            '--only-unmapped' => true,
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($decoded['only_unmapped'] ?? false);

        /** @var list<array<string, mixed>> $sections */
        $sections = $decoded['sections'];
        $obtained = $this->findSection($sections, 'fallout_wiki', 'obtained');
        /** @var list<array<string, mixed>> $rows */
        $rows = is_array($obtained['rows'] ?? null) ? $obtained['rows'] : [];

        self::assertCount(1, $rows);
        self::assertSame([
            'kind' => 'text',
            'value' => 'Project Paradise',
            'count' => 1,
            'file_count' => 1,
            'truthy_count' => null,
            'falsy_count' => null,
            'mapped_fields' => [],
        ], $rows[0]);
    }

    private function createFixtureProjectDir(): string
    {
        $projectDir = sys_get_temp_dir().'/f76-source-vocabulary-'.bin2hex(random_bytes(5));
        mkdir($projectDir.'/data/sources/fandom/plan_recipes', 0o777, true);
        mkdir($projectDir.'/data/sources/fallout_wiki/plan_recipes', 0o777, true);

        file_put_contents($projectDir.'/data/sources/fandom/plan_recipes/test.json', (string) json_encode([
            'resources' => [
                [
                    'availability' => [
                        'enemies' => true,
                        'vendors' => false,
                    ],
                    'columns' => [],
                ],
                [
                    'availability' => [
                        'enemies' => false,
                    ],
                    'columns' => [],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        file_put_contents($projectDir.'/data/sources/fallout_wiki/plan_recipes/test.json', (string) json_encode([
            'resources' => [
                [
                    'columns' => [
                        'obtained' => [
                            'text' => 'Project Paradise',
                            'icons' => ['Quest', 'Enemy Drop'],
                        ],
                        'type' => 'caps',
                    ],
                ],
                [
                    'columns' => [
                        'obtained' => ['Fallout 76 Locations', 'Bottle Cap'],
                        'type' => 'gold bullion',
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $projectDir;
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return array<string, mixed>
     */
    private function findSection(array $sections, string $provider, string $field): array
    {
        foreach ($sections as $section) {
            if (($section['provider'] ?? null) === $provider && ($section['field'] ?? null) === $field) {
                return $section;
            }
        }

        self::fail(sprintf('Section %s.%s not found.', $provider, $field));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function findRow(array $rows, string $kind, string $value): array
    {
        foreach ($rows as $row) {
            if (($row['kind'] ?? null) === $kind && ($row['value'] ?? null) === $value) {
                return $row;
            }
        }

        self::fail(sprintf('Row %s:%s not found.', $kind, $value));
    }
}
