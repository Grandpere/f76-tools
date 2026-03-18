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

namespace App\Catalog\UI\Console;

use App\Catalog\Application\Import\ItemImportExternalMetadataEnricher;
use App\Catalog\Application\Import\ItemImportSourceReader;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:data:report:source-vocabulary',
    description: 'Liste les valeurs brutes observees dans les snapshots de sources catalogue.',
)]
final class ReportItemSourceVocabularyCommand extends Command
{
    /**
     * @var array<string, list<string>>
     */
    private const SUPPORTED_FIELDS = [
        'fandom' => ['availability'],
        'fallout_wiki' => ['obtained', 'type'],
    ];

    public function __construct(
        private readonly ItemImportSourceReader $sourceReader,
        private readonly ItemImportExternalMetadataEnricher $externalMetadataEnricher,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Provider: fandom|fallout_wiki')
            ->addOption('field', null, InputOption::VALUE_REQUIRED, 'Champ brut: availability|obtained|type')
            ->addOption('only-unmapped', null, InputOption::VALUE_NONE, 'N affiche que les labels qui n alimentent encore aucun champ canonique.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format de sortie: text|json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $provider = $this->normalizeStringOption($input->getOption('provider'));
        $field = $this->normalizeStringOption($input->getOption('field'));
        $format = $this->normalizeStringOption($input->getOption('format'));
        $onlyUnmapped = (bool) $input->getOption('only-unmapped');

        if (!in_array($format, ['text', 'json'], true)) {
            $io->error('Format invalide. Utilise text ou json.');

            return Command::INVALID;
        }

        $targets = $this->resolveTargets($provider, $field);
        if (null === $targets) {
            $io->error('Combinaison --provider/--field invalide.');

            return Command::INVALID;
        }

        $projectDir = rtrim($this->kernel->getProjectDir(), '/');
        $sections = [];

        foreach ($targets as $target) {
            $sections[] = $this->buildSection($projectDir, $target['provider'], $target['field'], $onlyUnmapped);
        }

        if ('json' === $format) {
            $output->writeln((string) json_encode([
                'provider' => $provider,
                'field' => $field,
                'only_unmapped' => $onlyUnmapped,
                'sections' => $sections,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title('Item Source Vocabulary Report');
        $io->definitionList(
            ['Provider filter' => $provider ?? 'all'],
            ['Field filter' => $field ?? 'all'],
            ['Only unmapped' => $onlyUnmapped ? 'yes' : 'no'],
            ['Sections' => (string) count($sections)],
        );

        foreach ($sections as $index => $section) {
            if ($index > 0) {
                $output->writeln('');
            }

            $io->section(sprintf('%s.%s', $section['provider'], $section['field']));
            $io->definitionList(
                ['Files scanned' => (string) $section['files_scanned']],
                ['Rows scanned' => (string) $section['rows_scanned']],
                ['Distinct values' => (string) count($section['rows'])],
            );

            if ([] === $section['rows']) {
                $io->writeln('Aucune valeur observee.');

                continue;
            }

            $table = new Table($output);
            $table->setHeaders(['Kind', 'Value', 'Count', 'Files', 'Truthy', 'Falsy', 'Mapped']);

            foreach ($section['rows'] as $row) {
                $table->addRow([
                    $row['kind'],
                    $row['value'],
                    (string) $row['count'],
                    (string) $row['file_count'],
                    null !== $row['truthy_count'] ? (string) $row['truthy_count'] : '-',
                    null !== $row['falsy_count'] ? (string) $row['falsy_count'] : '-',
                    [] !== $row['mapped_fields'] ? implode(', ', $row['mapped_fields']) : '-',
                ]);
            }

            $table->render();
        }

        return Command::SUCCESS;
    }

    private function normalizeStringOption(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return '' === $normalized ? null : $normalized;
    }

    /**
     * @return list<array{provider:string, field:string}>|null
     */
    private function resolveTargets(?string $provider, ?string $field): ?array
    {
        if (null === $provider && null === $field) {
            return [
                ['provider' => 'fandom', 'field' => 'availability'],
                ['provider' => 'fallout_wiki', 'field' => 'obtained'],
                ['provider' => 'fallout_wiki', 'field' => 'type'],
            ];
        }

        if (null !== $provider) {
            $providerFields = self::SUPPORTED_FIELDS[$provider] ?? null;
            if (null === $providerFields) {
                return null;
            }

            if (null === $field) {
                return array_map(
                    static fn (string $providerField): array => ['provider' => $provider, 'field' => $providerField],
                    $providerFields,
                );
            }

            if (!in_array($field, $providerFields, true)) {
                return null;
            }

            return [['provider' => $provider, 'field' => $field]];
        }

        foreach (self::SUPPORTED_FIELDS as $candidateProvider => $fields) {
            if (in_array($field, $fields, true)) {
                return [['provider' => $candidateProvider, 'field' => $field]];
            }
        }

        return null;
    }

    /**
     * @return array{
     *     provider:string,
     *     field:string,
     *     files_scanned:int,
     *     rows_scanned:int,
     *     rows:list<array{
     *         kind:string,
     *         value:string,
     *         count:int,
     *         file_count:int,
     *         truthy_count:int|null,
     *         falsy_count:int|null,
     *         mapped_fields:list<string>
     *     }>
     * }
     */
    private function buildSection(string $projectDir, string $provider, string $field, bool $onlyUnmapped): array
    {
        $rootPath = $projectDir.'/data/sources/'.$provider;
        $files = is_dir($rootPath) ? $this->sourceReader->findImportFiles($rootPath) : [];
        $rowsScanned = 0;

        /** @var array<string, array{
         *     kind:string,
         *     value:string,
         *     count:int,
         *     files:array<string,true>,
         *     truthy_count:int|null,
         *     falsy_count:int|null,
         *     mapped_fields:list<string>
         * }> $entries
         */
        $entries = [];

        foreach ($files as $path) {
            $resources = $this->readResources($path);
            if (null === $resources) {
                continue;
            }

            /** @var array<int, array<string, mixed>> $resources */
            foreach ($resources as $resource) {
                ++$rowsScanned;
                $columns = $resource['columns'] ?? null;
                if (!is_array($columns)) {
                    $columns = [];
                }

                match ($field) {
                    'availability' => $this->collectAvailability($entries, $path, $resource['availability'] ?? null),
                    'obtained' => $this->collectObtained($entries, $path, $columns['obtained'] ?? null),
                    'type' => $this->collectScalarValue($entries, $path, 'value', $columns['type'] ?? null, $provider, $field),
                    default => null,
                };
            }
        }

        $rows = array_values(array_map(
            static fn (array $entry): array => [
                'kind' => $entry['kind'],
                'value' => $entry['value'],
                'count' => $entry['count'],
                'file_count' => count($entry['files']),
                'truthy_count' => $entry['truthy_count'],
                'falsy_count' => $entry['falsy_count'],
                'mapped_fields' => $entry['mapped_fields'],
            ],
            $entries,
        ));

        if ($onlyUnmapped) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => [] === $row['mapped_fields'],
            ));
        }

        usort($rows, static function (array $left, array $right): int {
            $countComparison = $right['count'] <=> $left['count'];
            if (0 !== $countComparison) {
                return $countComparison;
            }

            $kindComparison = strcmp($left['kind'], $right['kind']);
            if (0 !== $kindComparison) {
                return $kindComparison;
            }

            return strcmp($left['value'], $right['value']);
        });

        return [
            'provider' => $provider,
            'field' => $field,
            'files_scanned' => count($files),
            'rows_scanned' => $rowsScanned,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function readResources(string $path): ?array
    {
        try {
            $json = file_get_contents($path);
            if (false === $json) {
                return null;
            }

            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return null;
            }

            $resources = $decoded['resources'] ?? null;

            if (!is_array($resources) || !array_is_list($resources)) {
                return null;
            }

            $normalizedResources = [];
            foreach ($resources as $resource) {
                if (is_array($resource)) {
                    /** @var array<string, mixed> $normalizedResource */
                    $normalizedResource = $resource;
                    $normalizedResources[] = $normalizedResource;
                }
            }

            return $normalizedResources;
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @param array<string, array{
     *     kind:string,
     *     value:string,
     *     count:int,
     *     files:array<string,true>,
     *     truthy_count:int|null,
     *     falsy_count:int|null,
     *     mapped_fields:list<string>
     * }> $entries
     */
    private function collectAvailability(array &$entries, string $path, mixed $availability): void
    {
        if (!is_array($availability) || array_is_list($availability)) {
            return;
        }

        foreach ($availability as $key => $value) {
            $name = trim((string) $key);
            if ('' === $name) {
                continue;
            }

            $entryKey = 'flag:'.$name;
            $entries[$entryKey] ??= [
                'kind' => 'flag',
                'value' => $name,
                'count' => 0,
                'files' => [],
                'truthy_count' => 0,
                'falsy_count' => 0,
                'mapped_fields' => [$name],
            ];

            ++$entries[$entryKey]['count'];
            $entries[$entryKey]['files'][$path] = true;

            if ($this->isTruthyValue($value)) {
                ++$entries[$entryKey]['truthy_count'];
            } else {
                ++$entries[$entryKey]['falsy_count'];
            }
        }
    }

    /**
     * @param array<string, array{
     *     kind:string,
     *     value:string,
     *     count:int,
     *     files:array<string,true>,
     *     truthy_count:int|null,
     *     falsy_count:int|null,
     *     mapped_fields:list<string>
     * }> $entries
     */
    private function collectObtained(array &$entries, string $path, mixed $obtained): void
    {
        if (is_scalar($obtained) || null === $obtained) {
            $this->collectScalarValue($entries, $path, 'value', $obtained, 'fallout_wiki', 'obtained');

            return;
        }

        if (is_array($obtained) && array_is_list($obtained)) {
            foreach ($obtained as $value) {
                $this->collectScalarValue($entries, $path, 'icon', $value, 'fallout_wiki', 'obtained');
            }

            return;
        }

        if (!is_array($obtained)) {
            return;
        }

        $text = $obtained['text'] ?? null;
        $this->collectScalarValue($entries, $path, 'text', $text, 'fallout_wiki', 'obtained');

        $icons = $obtained['icons'] ?? null;
        if (!is_array($icons) || !array_is_list($icons)) {
            return;
        }

        foreach ($icons as $icon) {
            $this->collectScalarValue($entries, $path, 'icon', $icon, 'fallout_wiki', 'obtained');
        }
    }

    /**
     * @param array<string, array{
     *     kind:string,
     *     value:string,
     *     count:int,
     *     files:array<string,true>,
     *     truthy_count:int|null,
     *     falsy_count:int|null,
     *     mapped_fields:list<string>
     * }> $entries
     */
    private function collectScalarValue(array &$entries, string $path, string $kind, mixed $value, string $provider, string $field): void
    {
        if (!is_scalar($value)) {
            return;
        }

        $normalized = trim((string) $value);
        if ('' === $normalized) {
            return;
        }

        $entryKey = $kind.':'.$normalized;
        $entries[$entryKey] ??= [
            'kind' => $kind,
            'value' => $normalized,
            'count' => 0,
            'files' => [],
            'truthy_count' => null,
            'falsy_count' => null,
            'mapped_fields' => $this->resolveMappedFields($provider, $field, $normalized),
        ];

        ++$entries[$entryKey]['count'];
        $entries[$entryKey]['files'][$path] = true;
    }

    private function isTruthyValue(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => 0 !== $value,
            is_string($value) => in_array(strtolower(trim($value)), ['1', 'true', 'yes'], true),
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    private function resolveMappedFields(string $provider, string $field, string $value): array
    {
        if ('fandom' === $provider && 'availability' === $field) {
            return [$value];
        }

        if ('fallout_wiki' !== $provider) {
            return [];
        }

        $metadata = match ($field) {
            'obtained' => ['obtained' => [$value]],
            'type' => ['type' => $value],
            default => [],
        };

        if ([] === $metadata) {
            return [];
        }

        $enriched = $this->externalMetadataEnricher->enrich('fallout_wiki', $metadata);
        $mappedFields = [];

        foreach (['containers', 'enemies', 'seasonal_content', 'treasure_maps', 'quests', 'vendors', 'world_spawns'] as $candidateField) {
            if (true === ($enriched[$candidateField] ?? false)) {
                $mappedFields[] = $candidateField;
            }
        }

        $purchaseCurrency = $enriched['purchase_currency'] ?? null;
        if (is_string($purchaseCurrency) && '' !== $purchaseCurrency) {
            $mappedFields[] = 'purchase_currency';
        }

        sort($mappedFields);

        return $mappedFields;
    }
}
