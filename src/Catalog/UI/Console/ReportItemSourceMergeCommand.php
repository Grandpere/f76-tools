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
use App\Catalog\Application\Import\ItemSourceFieldMergeDecision;
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
    name: 'app:data:report:source-merge',
    description: 'Applique la politique de merge cross-source en lecture et affiche les champs retenus et conflits restants.',
)]
final class ReportItemSourceMergeCommand extends Command
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
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max d items compares.', '50')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format de sortie: text|json', 'text')
            ->addOption('show-clean', null, InputOption::VALUE_NONE, 'Inclut aussi les items sans conflit.');
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

        $items = $this->comparisonReadRepository->findItemsWithProviders($providerA, $providerB, $type, $limit);

        $rows = [];
        $conflictedItems = 0;
        $genericLabelItems = 0;
        $decisionCount = 0;
        $conflictCount = 0;

        foreach ($items as $item) {
            $result = $this->itemSourceMergePolicy->merge($item, $providerA, $providerB);
            if (null === $result) {
                continue;
            }

            $decisionCount += count($result->decisions);
            $conflictCount += count($result->conflicts);

            if ($this->hasGenericLabelDecision($result->decisions)) {
                ++$genericLabelItems;
            }

            if ([] !== $result->conflicts) {
                ++$conflictedItems;
            }

            if ([] === $result->conflicts && !$showClean) {
                continue;
            }

            $rows[] = [
                'type' => $item->getType()->value,
                'sourceId' => $item->getSourceId(),
                'label' => $result->label,
                'decisionCount' => count($result->decisions),
                'conflictCount' => count($result->conflicts),
                'decisions' => array_map(
                    static fn ($decision): array => $decision->toArray(),
                    $result->decisions,
                ),
                'conflicts' => array_map(
                    static fn ($conflict): array => $conflict->toArray(),
                    $result->conflicts,
                ),
            ];
        }

        if ('json' === $format) {
            $output->writeln((string) json_encode([
                'provider_a' => $providerA,
                'provider_b' => $providerB,
                'type' => $type?->value,
                'items_scanned' => count($items),
                'items_with_conflicts' => $conflictedItems,
                'items_with_generic_labels' => $genericLabelItems,
                'items_with_material_conflicts' => $conflictedItems,
                'decisions_total' => $decisionCount,
                'conflicts_total' => $conflictCount,
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title('Item Source Merge Report');
        $io->definitionList(
            ['Provider A' => $providerA],
            ['Provider B' => $providerB],
            ['Type' => null !== $type ? $type->value : 'all'],
            ['Items scanned' => (string) count($items)],
            ['Items with conflicts' => (string) $conflictedItems],
            ['Items with generic labels' => (string) $genericLabelItems],
            ['Items with material conflicts' => (string) $conflictedItems],
            ['Decisions total' => (string) $decisionCount],
            ['Conflicts total' => (string) $conflictCount],
            ['Rows shown' => (string) count($rows)],
        );

        if ([] === $rows) {
            $io->success('Aucun conflit a afficher avec la politique de merge actuelle.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Type', 'Source ID', 'Label', 'Retained', 'Conflicts']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['type'],
                (string) $row['sourceId'],
                $row['label'],
                (string) $row['decisionCount'],
                (string) $row['conflictCount'],
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

    /**
     * @param list<ItemSourceFieldMergeDecision> $decisions
     */
    private function hasGenericLabelDecision(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            if ('generic_label_confirmed_by_specific_target' === $decision->reason) {
                return true;
            }
        }

        return false;
    }
}
