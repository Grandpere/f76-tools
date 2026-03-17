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

use App\Catalog\Application\Nukacrypt\NukacryptRecord;
use App\Catalog\Application\Nukacrypt\NukacryptRecordLookup;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data:probe:nukacrypt-conflict',
    description: 'Compare des candidats source contre un form_id attendu via Nukacrypt.',
)]
final class ProbeNukacryptConflictCommand extends Command
{
    public function __construct(
        private readonly NukacryptRecordLookup $nukacryptRecordLookup,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('expected-form-id', null, InputOption::VALUE_REQUIRED, 'Form ID attendu a confirmer.')
            ->addOption('candidate', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Nom candidat a verifier.', [])
            ->addOption('editor-id', null, InputOption::VALUE_REQUIRED, 'Editor ID a verifier en plus si disponible.')
            ->addOption('signature', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Signature(s) ESM a filtrer.', ['BOOK'])
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format: text|json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expectedFormId = $input->getOption('expected-form-id');
        if (!is_scalar($expectedFormId) || '' === trim((string) $expectedFormId)) {
            $io->error('Option --expected-form-id invalide.');

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

        /** @var list<mixed> $rawCandidates */
        $rawCandidates = (array) $input->getOption('candidate');
        $candidates = array_values(array_filter(
            array_map(
                static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
                $rawCandidates,
            ),
            static fn (string $value): bool => '' !== $value,
        ));

        $editorIdOption = $input->getOption('editor-id');
        $editorId = is_scalar($editorIdOption) ? trim((string) $editorIdOption) : '';
        if ([] === $candidates && '' === $editorId) {
            $io->error('Ajoute au moins un --candidate ou un --editor-id.');

            return Command::INVALID;
        }

        /** @var list<mixed> $rawSignatures */
        $rawSignatures = (array) $input->getOption('signature');
        $signatures = array_values(array_filter(
            array_map(
                static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
                $rawSignatures,
            ),
            static fn (string $value): bool => '' !== $value,
        ));

        $expected = strtoupper(trim((string) $expectedFormId));
        $results = [];

        foreach ($candidates as $candidate) {
            try {
                $records = $this->nukacryptRecordLookup->search($candidate, $signatures);
                $results[] = [
                    'kind' => 'candidate',
                    'query' => $candidate,
                    'matchesExpected' => $this->hasExpectedFormId($records, $expected),
                    'error' => null,
                    'records' => array_map(
                        static fn (NukacryptRecord $record): array => $record->toArray(),
                        $records,
                    ),
                ];
            } catch (RuntimeException $exception) {
                $results[] = [
                    'kind' => 'candidate',
                    'query' => $candidate,
                    'matchesExpected' => false,
                    'error' => $exception->getMessage(),
                    'records' => [],
                ];
            }
        }

        if ('' !== $editorId) {
            try {
                $records = $this->nukacryptRecordLookup->searchByEditorId($editorId, $signatures);
                $results[] = [
                    'kind' => 'editor_id',
                    'query' => $editorId,
                    'matchesExpected' => $this->hasExpectedFormId($records, $expected),
                    'error' => null,
                    'records' => array_map(
                        static fn (NukacryptRecord $record): array => $record->toArray(),
                        $records,
                    ),
                ];
            } catch (RuntimeException $exception) {
                $results[] = [
                    'kind' => 'editor_id',
                    'query' => $editorId,
                    'matchesExpected' => false,
                    'error' => $exception->getMessage(),
                    'records' => [],
                ];
            }
        }

        if ('json' === $format) {
            $output->writeln((string) json_encode([
                'expected_form_id' => $expected,
                'results' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title('Nukacrypt Conflict Probe');
        $io->definitionList(['Expected form ID' => $expected]);

        $rows = [];
        foreach ($results as $result) {
            if (is_string($result['error'] ?? null) && '' !== $result['error']) {
                $rows[] = [
                    (string) $result['kind'],
                    (string) $result['query'],
                    'no',
                    '',
                    '[error] '.(string) $result['error'],
                    '',
                ];

                continue;
            }

            /** @var list<array<string, mixed>> $records */
            $records = $result['records'];
            foreach ($records as $record) {
                $formId = $record['form_id'] ?? '';
                $name = $record['name'] ?? '';
                $editor = $record['editor_id'] ?? '';

                $rows[] = [
                    (string) $result['kind'],
                    (string) $result['query'],
                    true === $result['matchesExpected'] ? 'yes' : 'no',
                    is_scalar($formId) ? (string) $formId : '',
                    is_scalar($name) ? (string) $name : '',
                    is_scalar($editor) ? (string) $editor : '',
                ];
            }

            if ([] === $result['records']) {
                $rows[] = [
                    (string) $result['kind'],
                    (string) $result['query'],
                    'no',
                    '',
                    '[no result]',
                    '',
                ];
            }
        }

        $io->table(
            ['Kind', 'Query', 'Matches expected', 'Form ID', 'Name', 'Editor ID'],
            $rows,
        );

        return Command::SUCCESS;
    }

    /**
     * @param list<NukacryptRecord> $records
     */
    private function hasExpectedFormId(array $records, string $expectedFormId): bool
    {
        foreach ($records as $record) {
            if (strtoupper($record->formId) === $expectedFormId) {
                return true;
            }
        }

        return false;
    }
}
