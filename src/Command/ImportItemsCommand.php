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

namespace App\Command;

use App\Catalog\Application\Import\ItemImportApplicationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:items:import',
    description: 'Importe les items depuis les fichiers JSON legendary_mods et minerva.',
)]
final class ImportItemsCommand extends Command
{
    public function __construct(
        private readonly ItemImportApplicationService $itemImportApplicationService,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Dossier contenant les JSON.', 'data')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse et affiche sans ecrire en base.')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Taille de lot Doctrine.', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pathArgRaw = $input->getArgument('path');
        if (!is_string($pathArgRaw) || '' === trim($pathArgRaw)) {
            $io->error('Argument "path" invalide.');

            return Command::INVALID;
        }

        $pathArg = $pathArgRaw;
        $rootPath = str_starts_with($pathArg, '/')
            ? $pathArg
            : rtrim($this->kernel->getProjectDir(), '/').'/'.ltrim($pathArg, '/');

        if (!is_dir($rootPath)) {
            $io->error(sprintf('Dossier introuvable: %s', $rootPath));

            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');

        $batchSizeRaw = $input->getOption('batch-size');
        if (!is_scalar($batchSizeRaw) || !is_numeric((string) $batchSizeRaw)) {
            $io->error('Option --batch-size invalide.');

            return Command::INVALID;
        }
        $batchSize = max(1, (int) $batchSizeRaw);

        $io->title('Import Items');
        $io->text(sprintf('Dossier: %s', $rootPath));
        $io->text(sprintf('Mode: %s', $dryRun ? 'DRY-RUN' : 'WRITE'));

        $result = $this->itemImportApplicationService->import($rootPath, $dryRun, $batchSize);
        $stats = $result->getStats();

        if (0 === $stats['files']) {
            $io->warning(sprintf('Aucun fichier JSON importable trouve dans %s', $rootPath));

            return Command::SUCCESS;
        }

        foreach ($result->getWarnings() as $warning) {
            $io->warning($warning);
        }

        $io->newLine();
        $io->definitionList(
            ['Fichiers' => (string) $stats['files']],
            ['Lignes lues' => (string) $stats['rows']],
            ['Crees' => (string) $stats['created']],
            ['Maj' => (string) $stats['updated']],
            ['Skips' => (string) $stats['skipped']],
            ['Warnings' => (string) $stats['warnings']],
            ['Erreurs' => (string) $stats['errors']],
            ['Trads EN' => (string) $stats['translations_en']],
            ['Trads DE' => (string) $stats['translations_de']],
        );

        return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;
    }

}
