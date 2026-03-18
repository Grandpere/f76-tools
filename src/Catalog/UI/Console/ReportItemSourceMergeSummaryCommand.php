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
use App\Catalog\Application\Import\ItemSourceMergePolicy;
use App\Catalog\Domain\Item\ItemTypeEnum;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data:report:source-merge-summary',
    description: 'Synthese par champ de la politique de merge cross-source.',
)]
final class ReportItemSourceMergeSummaryCommand extends Command
{
    public function __construct(
        private readonly ItemSourceComparisonReadRepository $comparisonReadRepository,
        private readonly ItemSourceMergePolicy $itemSourceMergePolicy,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('provider-a', null, InputOption::VALUE_REQUIRED, 'Premier provider.', 'fandom')
            ->addOption('provider-b', null, InputOption::VALUE_REQUIRED, 'Second provider.', 'fallout_wiki')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filtre type: book|misc')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max d items compares.', '200')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format de sortie: text|json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $providerA = $this->normalizeStringOption($input->getOption('provider-a'));
        $providerB = $this->normalizeStringOption($input->getOption('provider-b'));
        $format = $this->normalizeStringOption($input->getOption('format'));

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

        /** @var array<string, array{retained_total:int, retained_by_provider:array<string,int>, conflicts:int, generic_label_retained:int}> $summary */
        $summary = [];
        $itemsWithMerge = 0;
        $genericLabelItems = 0;
        $materialConflictItems = 0;

        foreach ($items as $item) {
            $result = $this->itemSourceMergePolicy->merge($item, $providerA, $providerB);
            if (null === $result) {
                continue;
            }

            ++$itemsWithMerge;
            $itemHasGenericLabelDecision = false;

            foreach ($result->decisions as $decision) {
                if (!isset($summary[$decision->field])) {
                    $summary[$decision->field] = [
                        'retained_total' => 0,
                        'retained_by_provider' => [],
                        'conflicts' => 0,
                        'generic_label_retained' => 0,
                    ];
                }

                ++$summary[$decision->field]['retained_total'];
                $summary[$decision->field]['retained_by_provider'][$decision->provider] ??= 0;
                ++$summary[$decision->field]['retained_by_provider'][$decision->provider];

                if ('generic_label_confirmed_by_specific_target' === $decision->reason) {
                    ++$summary[$decision->field]['generic_label_retained'];
                    $itemHasGenericLabelDecision = true;
                }
            }

            foreach ($result->conflicts as $conflict) {
                if (!isset($summary[$conflict->field])) {
                    $summary[$conflict->field] = [
                        'retained_total' => 0,
                        'retained_by_provider' => [],
                        'conflicts' => 0,
                        'generic_label_retained' => 0,
                    ];
                }

                ++$summary[$conflict->field]['conflicts'];
            }

            if ($itemHasGenericLabelDecision) {
                ++$genericLabelItems;
            }

            if ([] !== $result->conflicts) {
                ++$materialConflictItems;
            }
        }

        ksort($summary);

        if ('json' === $format) {
            $output->writeln((string) json_encode([
                'provider_a' => $providerA,
                'provider_b' => $providerB,
                'type' => $type?->value,
                'items_scanned' => count($items),
                'items_with_merge' => $itemsWithMerge,
                'items_with_generic_labels' => $genericLabelItems,
                'items_with_material_conflicts' => $materialConflictItems,
                'fields' => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title('Item Source Merge Summary');
        $io->definitionList(
            ['Provider A' => $providerA],
            ['Provider B' => $providerB],
            ['Type' => null !== $type ? $type->value : 'all'],
            ['Items scanned' => (string) count($items)],
            ['Items with merge' => (string) $itemsWithMerge],
            ['Items with generic labels' => (string) $genericLabelItems],
            ['Items with material conflicts' => (string) $materialConflictItems],
            ['Fields' => (string) count($summary)],
        );

        if ([] === $summary) {
            $io->success('Aucune synthese a afficher.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Field', 'Retained', 'Provider A', 'Provider B', 'Generic labels', 'Conflicts']);

        foreach ($summary as $field => $row) {
            $table->addRow([
                $field,
                (string) $row['retained_total'],
                (string) ($row['retained_by_provider'][$providerA] ?? 0),
                (string) ($row['retained_by_provider'][$providerB] ?? 0),
                (string) $row['generic_label_retained'],
                (string) $row['conflicts'],
            ]);
        }

        $table->render();

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
