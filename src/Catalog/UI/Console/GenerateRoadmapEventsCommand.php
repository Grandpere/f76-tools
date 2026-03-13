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

use App\Catalog\Application\Roadmap\GenerateRoadmapEventsFromSnapshotApplicationService;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roadmap:snapshot:parse-events',
    description: 'Parse un snapshot roadmap et genere les roadmap_event associes.',
)]
final class GenerateRoadmapEventsCommand extends Command
{
    public function __construct(
        private readonly GenerateRoadmapEventsFromSnapshotApplicationService $generateRoadmapEventsFromSnapshotApplicationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'ID du snapshot roadmap.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les evenements sans persister en base.')
            ->addOption('preview', null, InputOption::VALUE_REQUIRED, 'Nombre max de lignes affichees.', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $idRaw = $input->getArgument('id');
        $snapshotId = is_scalar($idRaw) && ctype_digit((string) $idRaw) ? (int) $idRaw : 0;
        if ($snapshotId <= 0) {
            $io->error('Snapshot id must be a positive integer.');

            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $previewRaw = $input->getOption('preview');
        $preview = is_scalar($previewRaw) && ctype_digit((string) $previewRaw) ? max(1, (int) $previewRaw) : 20;

        try {
            $events = $this->generateRoadmapEventsFromSnapshotApplicationService->generate($snapshotId, $dryRun);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->title('Roadmap snapshot parse');
        $io->definitionList(
            ['Snapshot ID' => (string) $snapshotId],
            ['Dry run' => $dryRun ? 'yes' : 'no'],
            ['Events parsed' => (string) count($events)],
        );

        foreach (array_slice($events, 0, $preview) as $event) {
            $io->writeln(sprintf(
                '- %s | %s -> %s',
                $event->title,
                $event->startsAt->format('Y-m-d H:i:s'),
                $event->endsAt->format('Y-m-d H:i:s'),
            ));
        }

        if ($dryRun) {
            $io->success('Dry run completed. No event persisted.');
        } else {
            $io->success('Roadmap events persisted.');
        }

        return Command::SUCCESS;
    }
}
