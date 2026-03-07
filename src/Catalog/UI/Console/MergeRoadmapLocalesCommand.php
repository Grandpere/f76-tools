<?php

declare(strict_types=1);

namespace App\Catalog\UI\Console;

use App\Catalog\Application\Roadmap\MergeRoadmapLocalesApplicationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roadmap:merge-locales',
    description: 'Fusionne trois snapshots roadmap (fr/en/de) en timeline canonique avec score de confiance.',
)]
final class MergeRoadmapLocalesCommand extends Command
{
    public function __construct(
        private readonly MergeRoadmapLocalesApplicationService $mergeRoadmapLocalesApplicationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('frSnapshotId', InputArgument::REQUIRED, 'ID du snapshot FR')
            ->addArgument('enSnapshotId', InputArgument::REQUIRED, 'ID du snapshot EN')
            ->addArgument('deSnapshotId', InputArgument::REQUIRED, 'ID du snapshot DE')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Calcule sans persister en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fr = (int) $input->getArgument('frSnapshotId');
        $en = (int) $input->getArgument('enSnapshotId');
        $de = (int) $input->getArgument('deSnapshotId');
        $dryRun = (bool) $input->getOption('dry-run');

        $result = $this->mergeRoadmapLocalesApplicationService->merge([
            'fr' => $fr,
            'en' => $en,
            'de' => $de,
        ], $dryRun);

        $io->definitionList(
            ['Total canonical events' => (string) $result->totalEvents],
            ['High confidence' => (string) $result->highConfidenceEvents],
            ['Medium confidence' => (string) $result->mediumConfidenceEvents],
            ['Low confidence' => (string) $result->lowConfidenceEvents],
            ['Dry run' => $dryRun ? 'yes' : 'no'],
        );

        foreach ($result->warnings as $warning) {
            $io->warning($warning);
        }

        if ($dryRun) {
            $io->success('Merge preview done.');

            return Command::SUCCESS;
        }

        $io->success('Canonical roadmap timeline saved.');

        return Command::SUCCESS;
    }
}

