<?php

declare(strict_types=1);

namespace App\Catalog\UI\Console;

use App\Catalog\Application\Roadmap\ApproveRoadmapSnapshotApplicationService;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roadmap:snapshot:approve',
    description: 'Approuve un snapshot OCR roadmap pour publication.',
)]
final class ApproveRoadmapSnapshotCommand extends Command
{
    public function __construct(
        private readonly ApproveRoadmapSnapshotApplicationService $approveRoadmapSnapshotApplicationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'ID du snapshot roadmap.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $idRaw = $input->getArgument('id');
        $id = is_scalar($idRaw) && ctype_digit((string) $idRaw) ? (int) $idRaw : 0;
        if ($id <= 0) {
            $io->error('Snapshot id must be a positive integer.');

            return Command::INVALID;
        }

        try {
            $this->approveRoadmapSnapshotApplicationService->approve($id);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Roadmap snapshot %d approved.', $id));

        return Command::SUCCESS;
    }
}

