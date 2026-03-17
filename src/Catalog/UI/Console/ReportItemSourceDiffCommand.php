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

use App\Catalog\Application\Import\ItemSourceComparisonReadRepository;
use App\Catalog\Domain\Entity\ItemExternalSourceEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data:report:source-diff',
    description: 'Compare deux providers de sources externes item par item et remonte les champs divergents.',
)]
final class ReportItemSourceDiffCommand extends Command
{
    /**
     * @var list<string>
     */
    private const IGNORED_METADATA_KEYS = [
        'source_generated_at',
        'source_page',
        'source_page_url',
        'source_section',
        'source_slug',
        'source_item_type',
    ];

    public function __construct(
        private readonly ItemSourceComparisonReadRepository $comparisonReadRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('provider-a', null, InputOption::VALUE_REQUIRED, 'Premier provider.', 'fandom')
            ->addOption('provider-b', null, InputOption::VALUE_REQUIRED, 'Second provider.', 'fallout_wiki')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filtre type: book|misc')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max d items compares.', '50')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format de sortie: text|json', 'text')
            ->addOption('show-equal', null, InputOption::VALUE_NONE, 'Inclut aussi les items sans diff.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $providerA = $this->normalizeProvider($input->getOption('provider-a'));
        $providerB = $this->normalizeProvider($input->getOption('provider-b'));
        $format = $this->normalizeStringOption($input->getOption('format'));
        $showEqual = (bool) $input->getOption('show-equal');

        if (null === $providerA || null === $providerB || $providerA === $providerB) {
            $io->error('Providers invalides. Utilise deux providers distincts.');

            return Command::INVALID;
        }

        if (!in_array($format, ['text', 'json'], true)) {
            $io->error('Format invalide. Utilise text ou json.');

            return Command::INVALID;
        }

        $limitRaw = $input->getOption('limit');
        if (!is_scalar($limitRaw) || !is_numeric((string) $limitRaw)) {
            $io->error('Option --limit invalide.');

            return Command::INVALID;
        }
        $limit = max(1, (int) $limitRaw);

        $type = $this->resolveType($input->getOption('type'));
        if (false === $type) {
            $io->error('Option --type invalide. Utilise book ou misc.');

            return Command::INVALID;
        }

        $items = $this->comparisonReadRepository->findItemsWithProviders($providerA, $providerB, $type, $limit);
        $rows = [];
        $itemsWithDiffs = 0;

        foreach ($items as $item) {
            $sourceA = $item->findExternalSourceByProvider($providerA);
            $sourceB = $item->findExternalSourceByProvider($providerB);
            if (null === $sourceA || null === $sourceB) {
                continue;
            }

            $diffs = $this->buildDiffs($sourceA, $sourceB);
            if ([] !== $diffs) {
                ++$itemsWithDiffs;
            }

            if ([] === $diffs && !$showEqual) {
                continue;
            }

            $rows[] = [
                'type' => $item->getType()->value,
                'sourceId' => $item->getSourceId(),
                'externalRef' => $sourceA->getExternalRef(),
                'label' => $this->resolveLabel($sourceA, $sourceB),
                'diffCount' => count($diffs),
                'diffKeys' => array_keys($diffs),
                'diffs' => $diffs,
            ];
        }

        if ('json' === $format) {
            $output->writeln((string) json_encode([
                'provider_a' => $providerA,
                'provider_b' => $providerB,
                'type' => $type?->value,
                'items_scanned' => count($items),
                'items_with_diffs' => $itemsWithDiffs,
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title('Item Source Diff Report');
        $io->definitionList(
            ['Provider A' => $providerA],
            ['Provider B' => $providerB],
            ['Type' => null !== $type ? $type->value : 'all'],
            ['Items scanned' => (string) count($items)],
            ['Items with diffs' => (string) $itemsWithDiffs],
            ['Rows shown' => (string) count($rows)],
        );

        if ([] === $rows) {
            $io->success('Aucune divergence a afficher.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Type', 'Source ID', 'Ref', 'Label', 'Diffs']);
        foreach ($rows as $row) {
            $table->addRow([
                $row['type'],
                (string) $row['sourceId'],
                $row['externalRef'],
                $row['label'],
                implode(', ', $row['diffKeys']),
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }

    private function normalizeProvider(mixed $value): ?string
    {
        return $this->normalizeStringOption($value);
    }

    private function normalizeStringOption(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return '' === $normalized ? null : $normalized;
    }

    private function resolveType(mixed $value): ItemTypeEnum|false|null
    {
        $normalized = $this->normalizeStringOption($value);
        if (null === $normalized) {
            return null;
        }

        return match ($normalized) {
            'book' => ItemTypeEnum::BOOK,
            'misc' => ItemTypeEnum::MISC,
            default => false,
        };
    }

    private function resolveLabel(ItemExternalSourceEntity $sourceA, ItemExternalSourceEntity $sourceB): string
    {
        $label = $this->extractComparableValue($sourceA->getMetadata(), 'name_en')
            ?? $this->extractComparableValue($sourceA->getMetadata(), 'name_de')
            ?? $this->extractComparableValue($sourceB->getMetadata(), 'name_en')
            ?? $this->extractComparableValue($sourceB->getMetadata(), 'name_de');

        return is_string($label) ? $label : $sourceA->getExternalRef();
    }

    /**
     * @return array<string, array{a:mixed, b:mixed}>
     */
    private function buildDiffs(ItemExternalSourceEntity $sourceA, ItemExternalSourceEntity $sourceB): array
    {
        $left = $this->extractComparableMap($sourceA);
        $right = $this->extractComparableMap($sourceB);

        $diffs = [];
        foreach (array_unique(array_merge(array_keys($left), array_keys($right))) as $key) {
            $leftValue = $left[$key] ?? null;
            $rightValue = $right[$key] ?? null;

            if ($this->normalizeForComparison($leftValue) === $this->normalizeForComparison($rightValue)) {
                continue;
            }

            $diffs[$key] = [
                'a' => $leftValue,
                'b' => $rightValue,
            ];
        }

        return $diffs;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractComparableMap(ItemExternalSourceEntity $source): array
    {
        $map = [
            'external_url' => $source->getExternalUrl(),
        ];

        $metadata = $source->getMetadata();
        if (!is_array($metadata)) {
            return $map;
        }

        foreach ($metadata as $key => $value) {
            $normalizedKey = (string) $key;
            if (in_array($normalizedKey, self::IGNORED_METADATA_KEYS, true)) {
                continue;
            }

            $map[$normalizedKey] = $value;
        }

        return $map;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function extractComparableValue(?array $metadata, string $key): mixed
    {
        if (!is_array($metadata) || !array_key_exists($key, $metadata)) {
            return null;
        }

        return $metadata[$key];
    }

    private function normalizeForComparison(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        try {
            return (string) json_encode($this->sortRecursively($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return serialize($value);
        }
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->sortRecursively(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $child) {
            $value[$key] = $this->sortRecursively($child);
        }

        return $value;
    }
}
