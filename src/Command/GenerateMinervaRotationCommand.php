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

use App\Catalog\Application\Minerva\MinervaRotationGenerationApplicationService;
use App\Catalog\Application\Minerva\MinervaRotationRegenerationApplicationService;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:minerva:generate-rotation',
    description: 'Genere la rotation Minerva en base sur une periode datee.',
)]
final class GenerateMinervaRotationCommand extends Command
{
    public function __construct(
        private readonly MinervaRotationGenerationApplicationService $generationService,
        private readonly MinervaRotationRegenerationApplicationService $regenerationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Date de debut (YYYY-MM-DD).')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Date de fin (YYYY-MM-DD).')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les fenetres generees sans ecriture DB.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $timezone = new DateTimeZone('America/New_York');
        $fromOption = $input->getOption('from');
        $toOption = $input->getOption('to');
        $from = is_string($fromOption) ? $this->parseDate($fromOption, true, $timezone) : null;
        $to = is_string($toOption) ? $this->parseDate($toOption, false, $timezone) : null;

        if (!$from instanceof DateTimeImmutable || !$to instanceof DateTimeImmutable) {
            $io->error('Options --from et --to obligatoires au format YYYY-MM-DD.');

            return Command::INVALID;
        }
        if ($to < $from) {
            $io->error('La date --to doit etre superieure ou egale a --from.');

            return Command::INVALID;
        }

        $rows = $this->generationService->generate($from, $to);
        $isDryRun = (bool) $input->getOption('dry-run');

        $io->title('Generation rotation Minerva');
        $io->text(sprintf('Periode: %s -> %s (America/New_York)', $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')));
        $io->text(sprintf('Fenetres generees: %d', count($rows)));

        if ($isDryRun) {
            foreach (array_slice($rows, 0, 8) as $row) {
                $io->text(sprintf(
                    '- list=%d | %s | %s -> %s',
                    $row['listCycle'],
                    $row['location'],
                    $row['startsAt']->format('Y-m-d H:i'),
                    $row['endsAt']->format('Y-m-d H:i'),
                ));
            }
            if (count($rows) > 8) {
                $io->text('... (tronque)');
            }

            return Command::SUCCESS;
        }

        $result = $this->regenerationService->regenerate($from, $to);

        $io->success(sprintf('Rotation regeneree. Supprimees=%d, inserees=%d.', $result['deleted'], $result['inserted']));

        return Command::SUCCESS;
    }

    private function parseDate(string $value, bool $isStart, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }
        $suffix = $isStart ? '00:00:00' : '23:59:59';

        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%s %s', $trimmed, $suffix), $timezone) ?: null;
    }
}
