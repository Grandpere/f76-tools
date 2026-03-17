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

use DateTimeImmutable;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[AsCommand(
    name: 'app:data:sync',
    description: 'Synchronise les fichiers JSON legendary mods et Minerva depuis Nukaknights.',
)]
class SyncDataCommand extends Command
{
    private const BASE_URL = 'https://nukaknights.com/ajax/home.html';
    private const MAX_ATTEMPTS = 3;
    private const BASE_BACKOFF_MS = 500;
    private const MIN_DELAY_BETWEEN_REQUESTS_MS = 150;
    private const MAX_DELAY_BETWEEN_REQUESTS_MS = 400;

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $onlyRaw = $input->getOption('only');
        $only = is_string($onlyRaw) ? strtolower(trim($onlyRaw)) : 'all';
        $runNukaknights = 'all' === $only || 'nukaknights' === $only;
        $runFandom = 'all' === $only || 'fandom' === $only;
        $runFalloutWiki = 'all' === $only || 'fallout-wiki' === $only;
        $formatRaw = $input->getOption('format');
        $format = is_string($formatRaw) ? strtolower(trim($formatRaw)) : 'text';
        $isJson = 'json' === $format;

        if (!$isJson) {
            $io->title('Data sync');
        }

        if ((!$runNukaknights && !$runFandom && !$runFalloutWiki) || !in_array($format, ['text', 'json'], true)) {
            $io->error('Options invalides. --only: all|nukaknights|fandom|fallout-wiki ; --format: text|json.');

            return Command::INVALID;
        }

        $projectDir = rtrim($this->kernel->getProjectDir(), '/');

        $errors = [];
        $updated = 0;
        $updatedNukaknights = 0;
        $nukaknightsSummary = null;
        $fandomCode = null;
        $falloutWikiCode = null;

        if ($runNukaknights) {
            if (!$isJson) {
                $io->section('Nukaknights');
            }
            $nukaknightsSummary = $this->syncNukaknights($projectDir, $errors, $io, !$isJson);
            $updated += $nukaknightsSummary['updated_files'];
            $updatedNukaknights = $nukaknightsSummary['updated_files'];
        }

        if ($runFandom) {
            if (!$isJson) {
                $io->section('Fandom');
            }
            $fandomCode = $this->runFandomSync($input, $output, $io);
        }

        if ($runFalloutWiki) {
            if (!$isJson) {
                $io->section('Fallout Wiki');
            }
            $falloutWikiCode = $this->runFalloutWikiSync($input, $output, $io);
        }

        $hasFailure = [] !== $errors
            || (null !== $fandomCode && Command::SUCCESS !== $fandomCode)
            || (null !== $falloutWikiCode && Command::SUCCESS !== $falloutWikiCode);
        $fandomStatus = null === $fandomCode ? 'skipped' : (Command::SUCCESS === $fandomCode ? 'ok' : 'failed');
        $falloutWikiStatus = null === $falloutWikiCode ? 'skipped' : (Command::SUCCESS === $falloutWikiCode ? 'ok' : 'failed');
        $exitCode = $hasFailure ? Command::FAILURE : Command::SUCCESS;
        if ('json' === $format) {
            $output->writeln((string) json_encode([
                'scope' => $only,
                'updated_files' => $updated,
                'updated_by_source' => [
                    'nukaknights' => $updatedNukaknights,
                    'fandom' => Command::SUCCESS === $fandomCode ? 1 : 0,
                    'fallout_wiki' => Command::SUCCESS === $falloutWikiCode ? 1 : 0,
                ],
                'nukaknights_index' => $nukaknightsSummary['index'] ?? null,
                'fandom_status' => $fandomStatus,
                'fallout_wiki_status' => $falloutWikiStatus,
                'errors_count' => count($errors),
                'errors' => $errors,
                'status' => Command::SUCCESS === $exitCode ? 'ok' : 'failed',
            ], JSON_THROW_ON_ERROR));

            return $exitCode;
        }

        $io->newLine();
        $io->definitionList(
            ['Updated files' => (string) $updated],
            ['Updated Nukaknights' => (string) $updatedNukaknights],
            ['Fandom sync' => $fandomStatus],
            ['Fallout Wiki sync' => $falloutWikiStatus],
            ['Errors' => (string) count($errors)],
        );
        if (is_array($nukaknightsSummary)) {
            $io->definitionList(
                ['Nukaknights output' => $nukaknightsSummary['output_directory']],
                ['Nukaknights index' => $nukaknightsSummary['index']],
            );
        }

        if ($hasFailure) {
            foreach ($errors as $error) {
                $io->error($error);
            }
            if (null !== $fandomCode && Command::SUCCESS !== $fandomCode) {
                $io->error('Fandom sync failed.');
            }
            if (null !== $falloutWikiCode && Command::SUCCESS !== $falloutWikiCode) {
                $io->error('Fallout Wiki sync failed.');
            }

            return $exitCode;
        }

        $io->success('Sync terminee.');

        return $exitCode;
    }

    protected function configure(): void
    {
        $this
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Scope sync: all|nukaknights|fandom|fallout-wiki', 'all')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text|json', 'text')
            ->addOption('fandom-output-dir', null, InputOption::VALUE_REQUIRED, 'Forwarded to app:data:sync:fandom --output-dir')
            ->addOption('fandom-page', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Forwarded to app:data:sync:fandom --page')
            ->addOption('fandom-no-delay', null, InputOption::VALUE_NONE, 'Forwarded to app:data:sync:fandom --no-delay')
            ->addOption('fallout-wiki-output-dir', null, InputOption::VALUE_REQUIRED, 'Forwarded to app:data:sync:fallout-wiki --output-dir')
            ->addOption('fallout-wiki-page', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Forwarded to app:data:sync:fallout-wiki --page')
            ->addOption('fallout-wiki-no-delay', null, InputOption::VALUE_NONE, 'Forwarded to app:data:sync:fallout-wiki --no-delay');
    }

    /**
     * @param list<string> $errors
     *
     * @return array{
     *     updated_files:int,
     *     output_directory:string,
     *     index:string,
     *     datasets:list<array{
     *         dataset:string,
     *         directory:string,
     *         files_synced:int,
     *         files_total:int,
     *         files:list<array{file:string,url:string}>
     *     }>
     * }
     */
    protected function syncNukaknights(string $projectDir, array &$errors, SymfonyStyle $io, bool $displayProgress): array
    {
        $httpClient = $this->createHttpClient();
        $generatedAt = new DateTimeImmutable()->format(DATE_ATOM);
        $outputDirectory = $projectDir.'/data/sources/nukaknights';

        $datasets = [
            'legendary_mods' => [
                'label' => 'Legendary mods',
                'directory' => $outputDirectory.'/legendary_mods',
                'entries' => array_map(
                    static fn (int $rank): array => [
                        'url' => self::BASE_URL.'?legendary_mods='.$rank.'&lang=en',
                        'file' => sprintf('legendary_mods_%d_en.json', $rank),
                    ],
                    range(1, 4),
                ),
            ],
            'minerva' => [
                'label' => 'Minerva',
                'directory' => $outputDirectory.'/minerva',
                'entries' => array_map(
                    static fn (int $list): array => [
                        'url' => self::BASE_URL.'?minerva='.$list.'&lang=en',
                        'file' => sprintf('minerva_%d_en.json', $list),
                    ],
                    range(61, 84),
                ),
            ],
        ];

        $updatedFiles = 0;
        $datasetSummaries = [];

        foreach ($datasets as $datasetName => $dataset) {
            $syncedInDataset = 0;
            $files = [];
            $entries = $dataset['entries'];

            foreach ($entries as $index => $entry) {
                $target = $dataset['directory'].'/'.$entry['file'];
                if ($this->syncFile($httpClient, $entry['url'], $target, $errors)) {
                    ++$updatedFiles;
                    ++$syncedInDataset;
                }

                $files[] = [
                    'file' => $entry['file'],
                    'url' => $entry['url'],
                ];

                if ($displayProgress) {
                    $io->text(sprintf(
                        '%s %d/%d -> %s',
                        $dataset['label'],
                        $index + 1,
                        count($entries),
                        str_replace($projectDir.'/', '', $target),
                    ));
                }
            }

            $datasetSummaries[] = [
                'dataset' => $datasetName,
                'directory' => str_replace($projectDir.'/', '', $dataset['directory']),
                'files_synced' => $syncedInDataset,
                'files_total' => count($entries),
                'files' => $files,
            ];

            if ($displayProgress) {
                $io->text(sprintf(
                    '%s summary: %d/%d files synced',
                    $dataset['label'],
                    $syncedInDataset,
                    count($entries),
                ));
            }
        }

        $indexPath = $outputDirectory.'/index.json';
        $this->writeJson($indexPath, [
            'generated_at' => $generatedAt,
            'source' => 'nukaknights.com',
            'datasets_count' => count($datasetSummaries),
            'files_total' => array_sum(array_map(static fn (array $summary): int => $summary['files_total'], $datasetSummaries)),
            'datasets' => $datasetSummaries,
        ]);

        return [
            'updated_files' => $updatedFiles,
            'output_directory' => str_replace($projectDir.'/', '', $outputDirectory),
            'index' => str_replace($projectDir.'/', '', $indexPath),
            'datasets' => $datasetSummaries,
        ];
    }

    /**
     * @param list<string> $errors
     */
    protected function syncFile(HttpClientInterface $httpClient, string $url, string $target, array &$errors): bool
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $this->naturalDelay();

            try {
                $response = $httpClient->request('GET', $url);
                $statusCode = $response->getStatusCode();
                if (200 !== $statusCode) {
                    if ($attempt < self::MAX_ATTEMPTS && $this->isRetryableStatus($statusCode)) {
                        $this->backoff($attempt);
                        continue;
                    }

                    $errors[] = sprintf('HTTP %d sur %s', $statusCode, $url);

                    return false;
                }

                $content = $response->getContent();
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded) || !array_is_list($decoded)) {
                    $errors[] = sprintf('Payload invalide (liste JSON attendue): %s', $url);

                    return false;
                }

                if (false === file_put_contents($target, $content)) {
                    $errors[] = sprintf('Ecriture impossible: %s', $target);

                    return false;
                }

                return true;
            } catch (ExceptionInterface|JsonException $e) {
                if ($attempt < self::MAX_ATTEMPTS) {
                    $this->backoff($attempt);
                    continue;
                }

                $errors[] = sprintf('%s (%s)', $e->getMessage(), $url);

                return false;
            }
        }

        return false;
    }

    protected function createHttpClient(): HttpClientInterface
    {
        return HttpClient::create([
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'f76-data-sync-experimentation/1.0 (+https://github.com/Grandpere/f76-tools)',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function writeJson(string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($path, $encoded."\n");
    }

    private function isRetryableStatus(int $statusCode): bool
    {
        return 429 === $statusCode || $statusCode >= 500;
    }

    private function backoff(int $attempt): void
    {
        $multiplier = 1 << ($attempt - 1);
        $delayMs = self::BASE_BACKOFF_MS * $multiplier;
        usleep($delayMs * 1000);
    }

    private function naturalDelay(): void
    {
        $delayMs = random_int(self::MIN_DELAY_BETWEEN_REQUESTS_MS, self::MAX_DELAY_BETWEEN_REQUESTS_MS);
        usleep($delayMs * 1000);
    }

    private function runFandomSync(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        return $this->runDelegatedSync(
            $input,
            $output,
            $io,
            'app:data:sync:fandom',
            'Fandom',
            'fandom-output-dir',
            'fandom-no-delay',
        );
    }

    private function runFalloutWikiSync(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        return $this->runDelegatedSync(
            $input,
            $output,
            $io,
            'app:data:sync:fallout-wiki',
            'Fallout Wiki',
            'fallout-wiki-output-dir',
            'fallout-wiki-no-delay',
        );
    }

    private function runDelegatedSync(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        string $commandName,
        string $label,
        string $outputDirOptionName,
        string $noDelayOptionName,
    ): int {
        $application = $this->getApplication();
        if (null === $application) {
            $io->error(sprintf('Console application is not available to run %s sync.', $label));

            return Command::FAILURE;
        }

        try {
            $command = $application->find($commandName);
        } catch (Throwable $exception) {
            $io->error(sprintf('Unable to find %s command: %s', $commandName, $exception->getMessage()));

            return Command::FAILURE;
        }

        /** @var array<string, mixed> $arguments */
        $arguments = [];
        $outputDir = $input->getOption($outputDirOptionName);
        if (is_string($outputDir) && '' !== trim($outputDir)) {
            $arguments['--output-dir'] = trim($outputDir);
        }
        if ('app:data:sync:fandom' === $commandName) {
            $pages = $input->getOption('fandom-page');
            if (is_array($pages) && [] !== $pages) {
                $arguments['--page'] = array_values(array_filter($pages, static fn (mixed $page): bool => is_scalar($page) && '' !== trim((string) $page)));
            }
        }
        if ('app:data:sync:fallout-wiki' === $commandName) {
            $pages = $input->getOption('fallout-wiki-page');
            if (is_array($pages) && [] !== $pages) {
                $arguments['--page'] = array_values(array_filter($pages, static fn (mixed $page): bool => is_scalar($page) && '' !== trim((string) $page)));
            }
        }
        if ((bool) $input->getOption($noDelayOptionName)) {
            $arguments['--no-delay'] = true;
        }

        return $command->run(new ArrayInput($arguments), $output);
    }
}
