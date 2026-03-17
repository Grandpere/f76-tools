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

use App\Catalog\Application\Import\ItemSourceCollisionReadRepository;
use App\Catalog\Domain\Item\ItemTypeEnum;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data:report:source-collisions',
    description: 'Liste les external_ref rattaches a plusieurs items entre deux providers.',
)]
final class ReportItemSourceCollisionsCommand extends Command
{
    public function __construct(
        private readonly ItemSourceCollisionReadRepository $collisionReadRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('provider-a', null, InputOption::VALUE_REQUIRED, 'Premier provider.', 'fandom')
            ->addOption('provider-b', null, InputOption::VALUE_REQUIRED, 'Second provider.', 'fallout_wiki')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filtre type: book|misc')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max de collisions.', '50')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format de sortie: text|json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $providerA = $this->normalizeStringOption($input->getOption('provider-a'));
        $providerB = $this->normalizeStringOption($input->getOption('provider-b'));
        $format = $this->normalizeStringOption($input->getOption('format'));
        $type = $this->resolveType($input->getOption('type'));

        if (null === $providerA || null === $providerB || $providerA === $providerB) {
            $io->error('Providers invalides. Utilise deux providers distincts.');

            return Command::INVALID;
        }

        if (!in_array($format, ['text', 'json'], true)) {
            $io->error('Format invalide. Utilise text ou json.');

            return Command::INVALID;
        }

        if (false === $type) {
            $io->error('Option --type invalide. Utilise book ou misc.');

            return Command::INVALID;
        }

        $limitRaw = $input->getOption('limit');
        if (!is_scalar($limitRaw) || !is_numeric((string) $limitRaw)) {
            $io->error('Option --limit invalide.');

            return Command::INVALID;
        }

        $rows = $this->collisionReadRepository->findExternalRefCollisions($providerA, $providerB, $type, (int) $limitRaw);

        if ('json' === $format) {
            $output->writeln((string) json_encode([
                'provider_a' => $providerA,
                'provider_b' => $providerB,
                'type' => $type?->value,
                'count' => count($rows),
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title('Item Source Collision Report');
        $io->definitionList(
            ['Provider A' => $providerA],
            ['Provider B' => $providerB],
            ['Type' => null !== $type ? $type->value : 'all'],
            ['Collisions' => (string) count($rows)],
        );

        if ([] === $rows) {
            $io->success('Aucune collision detectee.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Type', 'External Ref', 'Items', 'Providers', 'Source IDs']);
        foreach ($rows as $row) {
            $table->addRow([
                $row['type'],
                $row['externalRef'],
                (string) $row['itemCount'],
                implode(', ', $row['providers']),
                implode(', ', array_map('strval', $row['sourceIds'])),
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
