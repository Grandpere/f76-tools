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
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Application\Roadmap\RoadmapTitleComparisonService;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use App\Catalog\Domain\Roadmap\RoadmapSnapshotStatusEnum;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roadmap:benchmark-ocr',
    description: 'Compare les titres OCR d un snapshot avec la reference manual.json de meme saison/locale.',
)]
final class BenchmarkRoadmapOcrAgainstManualCommand extends Command
{
    public function __construct(
        private readonly RoadmapSnapshotWriteRepository $snapshotWriteRepository,
        private readonly GenerateRoadmapEventsFromSnapshotApplicationService $generateRoadmapEventsFromSnapshotApplicationService,
        private readonly RoadmapTitleComparisonService $roadmapTitleComparisonService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ocr-id', InputArgument::REQUIRED, 'ID snapshot OCR a evaluer')
            ->addOption('manual-id', null, InputOption::VALUE_REQUIRED, 'ID snapshot manual.json de reference')
            ->addOption('top', null, InputOption::VALUE_REQUIRED, 'Nombre max d ecarts affiches', '12');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ocrSnapshotId = $this->toPositiveInt($input->getArgument('ocr-id'));
        if ($ocrSnapshotId <= 0) {
            $io->error('ocr-id must be a positive integer.');

            return Command::INVALID;
        }

        $top = max(1, $this->toPositiveInt($input->getOption('top')));
        $ocrSnapshot = $this->snapshotWriteRepository->findOneWithEventsById($ocrSnapshotId);
        if (!$ocrSnapshot instanceof RoadmapSnapshotEntity) {
            $io->error(sprintf('OCR snapshot #%d not found.', $ocrSnapshotId));

            return Command::FAILURE;
        }

        $manualSnapshot = $this->resolveManualSnapshot($input, $ocrSnapshot);
        if (!$manualSnapshot instanceof RoadmapSnapshotEntity) {
            $io->error('No matching approved manual.json snapshot found for this season/locale. Use --manual-id.');

            return Command::FAILURE;
        }

        try {
            $ocrEvents = $this->generateRoadmapEventsFromSnapshotApplicationService->generate($ocrSnapshotId, true);
        } catch (RuntimeException $exception) {
            $io->error('Unable to parse OCR snapshot: '.$exception->getMessage());

            return Command::FAILURE;
        }

        /** @var list<\App\Catalog\Domain\Entity\RoadmapEventEntity> $manualEvents */
        $manualEvents = array_values($manualSnapshot->getEvents()->toArray());
        $comparison = $this->roadmapTitleComparisonService->compareParsedToManual($ocrEvents, $manualEvents);

        $io->title('Roadmap OCR benchmark');
        $io->definitionList(
            ['OCR snapshot' => sprintf('#%d (%s, season %s)', $ocrSnapshotId, strtoupper($ocrSnapshot->getLocale()), $this->seasonLabel($ocrSnapshot))],
            ['Manual snapshot' => sprintf('#%d', $manualSnapshot->getId() ?? 0)],
            ['OCR events' => (string) $comparison['total_ocr']],
            ['Manual events' => (string) $comparison['total_manual']],
            ['Matched windows' => (string) $comparison['matched_windows']],
            ['Window matching mode' => (string) $comparison['window_mode']],
            ['Exact title matches' => (string) $comparison['exact_matches']],
            ['Average similarity' => sprintf('%.2f%%', $comparison['average_similarity'] * 100.0)],
            ['Placeholders' => (string) $comparison['placeholder_count']],
            ['Short titles (<=1 word)' => (string) $comparison['short_title_count']],
            ['Unmatched OCR windows' => (string) $comparison['unmatched_ocr_windows']],
        );

        $mismatches = array_slice($comparison['mismatches'], 0, $top);
        if ([] === $mismatches) {
            $io->success('No title mismatch detected on matched windows.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Window', 'Similarity', 'OCR', 'Manual']);
        foreach ($mismatches as $mismatch) {
            $table->addRow([
                $mismatch['window'],
                sprintf('%.1f%%', $mismatch['similarity'] * 100.0),
                $mismatch['ocr_title'],
                $mismatch['manual_title'],
            ]);
        }
        $table->render();

        $io->warning(sprintf('Showing %d/%d mismatches.', count($mismatches), count($comparison['mismatches'])));

        return Command::SUCCESS;
    }

    private function resolveManualSnapshot(InputInterface $input, RoadmapSnapshotEntity $ocrSnapshot): ?RoadmapSnapshotEntity
    {
        $manualId = $this->toPositiveInt($input->getOption('manual-id'));
        if ($manualId > 0) {
            $manualSnapshot = $this->snapshotWriteRepository->findOneWithEventsById($manualId);

            return $manualSnapshot instanceof RoadmapSnapshotEntity ? $manualSnapshot : null;
        }

        $season = $ocrSnapshot->getSeason();
        $locale = $ocrSnapshot->getLocale();
        $candidates = $this->snapshotWriteRepository->findRecent(1000, $season);
        foreach ($candidates as $snapshot) {
            if (
                $snapshot->getLocale() === $locale
                && $snapshot->getStatus() === RoadmapSnapshotStatusEnum::APPROVED
                && $snapshot->getOcrProvider() === 'manual.json'
            ) {
                $withEvents = $this->snapshotWriteRepository->findOneWithEventsById($snapshot->getId() ?? 0);
                if ($withEvents instanceof RoadmapSnapshotEntity) {
                    return $withEvents;
                }
            }
        }

        return null;
    }

    private function toPositiveInt(mixed $value): int
    {
        if (!is_scalar($value)) {
            return 0;
        }
        $string = trim((string) $value);
        if (!ctype_digit($string)) {
            return 0;
        }

        return (int) $string;
    }

    private function seasonLabel(RoadmapSnapshotEntity $snapshot): string
    {
        $season = $snapshot->getSeason();
        if (null === $season) {
            return 'n/a';
        }

        return (string) $season->getSeasonNumber();
    }
}
