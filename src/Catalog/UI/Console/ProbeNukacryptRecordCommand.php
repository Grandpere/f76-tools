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

use App\Catalog\Application\Nukacrypt\NukacryptRecordLookup;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data:probe:nukacrypt-record',
    description: 'Interroge Nukacrypt par recherche ciblee pour arbitrer un conflit source.',
)]
final class ProbeNukacryptRecordCommand extends Command
{
    public function __construct(
        private readonly NukacryptRecordLookup $nukacryptRecordLookup,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('query', InputArgument::REQUIRED, 'Nom ou recherche ciblee a envoyer a Nukacrypt.')
            ->addOption('signature', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Signature(s) ESM a filtrer.', ['BOOK'])
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format: text|json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $query = $input->getArgument('query');
        if (!is_scalar($query) || '' === trim((string) $query)) {
            $io->error('Argument query invalide.');

            return Command::INVALID;
        }

        $formatOption = $input->getOption('format');
        if (!is_scalar($formatOption)) {
            $io->error('Format invalide. Utilise text ou json.');

            return Command::INVALID;
        }

        $format = strtolower(trim((string) $formatOption));
        if (!in_array($format, ['text', 'json'], true)) {
            $io->error('Format invalide. Utilise text ou json.');

            return Command::INVALID;
        }

        /** @var list<mixed> $rawSignatures */
        $rawSignatures = (array) $input->getOption('signature');

        try {
            $records = $this->nukacryptRecordLookup->search(
                (string) $query,
                array_values(array_filter(
                    array_map(
                        static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                        $rawSignatures,
                    ),
                    static fn (string $value): bool => '' !== trim($value),
                )),
            );
        } catch (RuntimeException $exception) {
            $io->error('Probe Nukacrypt en echec: '.$exception->getMessage());

            return Command::FAILURE;
        }

        if ('json' === $format) {
            $output->writeln((string) json_encode(array_map(
                static fn ($record): array => $record->toArray(),
                $records,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title('Nukacrypt Record Probe');
        if ([] === $records) {
            $io->warning('Aucun record trouve.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Form ID', 'Name', 'Editor ID', 'Signature', 'Updated at'],
            array_map(
                static fn ($record): array => [
                    $record->formId,
                    $record->name ?? '',
                    $record->editorId ?? '',
                    $record->signature ?? '',
                    $record->updatedAt ?? '',
                ],
                $records,
            ),
        );

        return Command::SUCCESS;
    }
}
