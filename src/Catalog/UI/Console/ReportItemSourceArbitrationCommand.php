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
use App\Catalog\Application\Nukacrypt\NukacryptRecord;
use App\Catalog\Application\Nukacrypt\NukacryptRecordLookup;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Entity\ItemExternalSourceEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data:report:source-arbitration',
    description: 'Arbitre les conflits de noms entre deux providers via Nukacrypt.',
)]
final class ReportItemSourceArbitrationCommand extends Command
{
    /**
     * @var list<string>
     */
    private const NAME_FIELDS = ['name_en', 'name', 'name_de'];

    /**
     * @var array<string, list<NukacryptRecord>>
     */
    private array $lookupCache = [];

    public function __construct(
        private readonly ItemSourceComparisonReadRepository $comparisonReadRepository,
        private readonly NukacryptRecordLookup $nukacryptRecordLookup,
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
            ->addOption('signature', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Signature(s) Nukacrypt a filtrer. Laisser vide pour ne rien envoyer.', [])
            ->addOption('show-clean', null, InputOption::VALUE_NONE, 'Inclut aussi les items sans conflit de nom.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $providerA = $this->normalizeStringOption($input->getOption('provider-a'));
        $providerB = $this->normalizeStringOption($input->getOption('provider-b'));
        $format = $this->normalizeStringOption($input->getOption('format'));
        $showClean = (bool) $input->getOption('show-clean');

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

        /** @var list<mixed> $rawSignatures */
        $rawSignatures = (array) $input->getOption('signature');
        $signatures = array_values(array_filter(
            array_map(
                static fn (mixed $value): string => is_scalar($value) ? strtoupper(trim((string) $value)) : '',
                $rawSignatures,
            ),
            static fn (string $value): bool => '' !== $value,
        ));

        $items = $this->comparisonReadRepository->findItemsWithProviders($providerA, $providerB, $type, $limit);

        $rows = [];
        $conflictItems = 0;
        $resolvedItems = 0;
        $unresolvedItems = 0;

        foreach ($items as $item) {
            $row = $this->buildArbitrationRow($item, $providerA, $providerB, $signatures);
            if (null === $row) {
                if ($showClean) {
                    $rows[] = [
                        'type' => $item->getType()->value,
                        'sourceId' => $item->getSourceId(),
                        'expectedFormId' => null,
                        'label' => (string) $item->getSourceId(),
                        'providerA' => $providerA,
                        'providerB' => $providerB,
                        'field' => null,
                        'candidateA' => null,
                        'candidateB' => null,
                        'verdict' => 'no_name_conflict',
                        'matchProvider' => null,
                        'recordsA' => [],
                        'recordsB' => [],
                        'errorA' => null,
                        'errorB' => null,
                    ];
                }

                continue;
            }

            ++$conflictItems;

            if (in_array($row['verdict'], ['confirmed_provider_a', 'confirmed_provider_b', 'provider_a_more_specific_confirmed', 'provider_b_more_specific_confirmed'], true)) {
                ++$resolvedItems;
            } else {
                ++$unresolvedItems;
            }

            $rows[] = $row;
        }

        if ('json' === $format) {
            $output->writeln((string) json_encode([
                'provider_a' => $providerA,
                'provider_b' => $providerB,
                'type' => $type?->value,
                'items_scanned' => count($items),
                'items_with_name_conflicts' => $conflictItems,
                'resolved_items' => $resolvedItems,
                'unresolved_items' => $unresolvedItems,
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title('Item Source Arbitration Report');
        $io->definitionList(
            ['Provider A' => $providerA],
            ['Provider B' => $providerB],
            ['Type' => null !== $type ? $type->value : 'all'],
            ['Items scanned' => (string) count($items)],
            ['Items with name conflicts' => (string) $conflictItems],
            ['Resolved items' => (string) $resolvedItems],
            ['Unresolved items' => (string) $unresolvedItems],
            ['Rows shown' => (string) count($rows)],
        );

        if ([] === $rows) {
            $io->success('Aucun conflit de nom a arbitrer.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Type', 'Source ID', 'Form ID', 'Field', 'Candidate A', 'Candidate B', 'Verdict']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['type'],
                is_scalar($row['sourceId']) ? (string) $row['sourceId'] : '',
                $row['expectedFormId'] ?? '',
                $row['field'] ?? '',
                $row['candidateA'] ?? '',
                $row['candidateB'] ?? '',
                $row['verdict'],
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $signatures
     *
     * @return array<string, mixed>|null
     */
    private function buildArbitrationRow(ItemEntity $item, string $providerA, string $providerB, array $signatures): ?array
    {
        $sourceA = $item->findExternalSourceByProvider($providerA);
        $sourceB = $item->findExternalSourceByProvider($providerB);
        if (null === $sourceA || null === $sourceB) {
            return null;
        }

        $conflict = $this->resolveNameConflict($sourceA, $sourceB);
        if (null === $conflict) {
            return null;
        }

        $expectedFormId = strtoupper($sourceA->getExternalRef());

        [$recordsA, $errorA] = $this->lookupCandidate($conflict['candidateA'], $signatures);
        [$recordsB, $errorB] = $this->lookupCandidate($conflict['candidateB'], $signatures);

        $matchingRecordsA = $this->filterMatchingRecords($recordsA, $expectedFormId);
        $matchingRecordsB = $this->filterMatchingRecords($recordsB, $expectedFormId);
        $matchesA = [] !== $matchingRecordsA;
        $matchesB = [] !== $matchingRecordsB;

        if (null !== $errorA || null !== $errorB) {
            $verdict = 'lookup_error';
        } elseif ($matchesA && !$matchesB) {
            $verdict = 'confirmed_provider_a';
        } elseif (!$matchesA && $matchesB) {
            $verdict = 'confirmed_provider_b';
        } elseif ($this->isSpecificVariantPreferred($conflict['candidateA'], $conflict['candidateB']) && count($recordsB) > count($matchingRecordsB)) {
            $verdict = 'provider_a_more_specific_confirmed';
        } elseif ($this->isSpecificVariantPreferred($conflict['candidateB'], $conflict['candidateA']) && count($recordsA) > count($matchingRecordsA)) {
            $verdict = 'provider_b_more_specific_confirmed';
        } elseif ($matchesA) {
            $verdict = 'both_match_expected';
        } elseif ([] === $recordsA && [] === $recordsB) {
            $verdict = 'no_result';
        } else {
            $verdict = 'no_expected_match';
        }

        $matchProvider = match ($verdict) {
            'confirmed_provider_a' => $providerA,
            'confirmed_provider_b' => $providerB,
            'provider_a_more_specific_confirmed' => $providerA,
            'provider_b_more_specific_confirmed' => $providerB,
            default => null,
        };

        return [
            'type' => $item->getType()->value,
            'sourceId' => $item->getSourceId(),
            'expectedFormId' => $expectedFormId,
            'label' => $conflict['candidateB'],
            'providerA' => $providerA,
            'providerB' => $providerB,
            'field' => $conflict['field'],
            'candidateA' => $conflict['candidateA'],
            'candidateB' => $conflict['candidateB'],
            'verdict' => $verdict,
            'matchProvider' => $matchProvider,
            'recordsATotal' => count($recordsA),
            'recordsBTotal' => count($recordsB),
            'matchingRecordsATotal' => count($matchingRecordsA),
            'matchingRecordsBTotal' => count($matchingRecordsB),
            'recordsA' => array_map(
                static fn (NukacryptRecord $record): array => $record->toArray(),
                $matchesA ? $matchingRecordsA : $recordsA,
            ),
            'recordsB' => array_map(
                static fn (NukacryptRecord $record): array => $record->toArray(),
                $matchesB ? $matchingRecordsB : $recordsB,
            ),
            'errorA' => $errorA,
            'errorB' => $errorB,
        ];
    }

    /**
     * @return array{field:string,candidateA:string,candidateB:string}|null
     */
    private function resolveNameConflict(ItemExternalSourceEntity $sourceA, ItemExternalSourceEntity $sourceB): ?array
    {
        $metadataA = $sourceA->getMetadata() ?? [];
        $metadataB = $sourceB->getMetadata() ?? [];

        foreach (self::NAME_FIELDS as $field) {
            $candidateA = $this->extractString($metadataA[$field] ?? null);
            $candidateB = $this->extractString($metadataB[$field] ?? null);

            if (null === $candidateA || null === $candidateB) {
                continue;
            }

            if ($this->normalizeText($candidateA) === $this->normalizeText($candidateB)) {
                continue;
            }

            return [
                'field' => $field,
                'candidateA' => $candidateA,
                'candidateB' => $candidateB,
            ];
        }

        return null;
    }

    /**
     * @param list<string> $signatures
     *
     * @return array{0:list<NukacryptRecord>,1:?string}
     */
    private function lookupCandidate(string $candidate, array $signatures): array
    {
        $cacheKey = strtolower($candidate).'|'.implode(',', $signatures);
        if (array_key_exists($cacheKey, $this->lookupCache)) {
            return [$this->lookupCache[$cacheKey], null];
        }

        try {
            $records = $this->nukacryptRecordLookup->search($candidate, $signatures);
            $this->lookupCache[$cacheKey] = $records;

            return [$records, null];
        } catch (RuntimeException $exception) {
            return [[], $exception->getMessage()];
        }
    }

    /**
     * @param list<NukacryptRecord> $records
     *
     * @return list<NukacryptRecord>
     */
    private function filterMatchingRecords(array $records, string $expectedFormId): array
    {
        return array_values(array_filter(
            $records,
            static fn (NukacryptRecord $record): bool => strtoupper($record->formId) === strtoupper($expectedFormId),
        ));
    }

    private function extractString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return '' === $normalized ? null : $normalized;
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized);

        return trim((string) $normalized);
    }

    private function isSpecificVariantPreferred(string $specificCandidate, string $genericCandidate): bool
    {
        $specificHasParenthetical = str_contains($specificCandidate, '(') && str_contains($specificCandidate, ')');
        $genericHasParenthetical = str_contains($genericCandidate, '(') && str_contains($genericCandidate, ')');

        if (!$specificHasParenthetical || $genericHasParenthetical) {
            return false;
        }

        $normalizedSpecific = $this->normalizeText($specificCandidate);
        $normalizedGeneric = $this->normalizeText($genericCandidate);

        return '' !== $normalizedGeneric && str_starts_with($normalizedSpecific, $normalizedGeneric);
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
}
