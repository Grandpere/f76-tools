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

use App\Catalog\Application\Minerva\MinervaRotationRefresher;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:minerva:refresh-rotation',
    description: 'Verifie la couverture Minerva et regenere la plage si des fenetres manquent.',
)]
final class RefreshMinervaRotationCommand extends Command
{
    public function __construct(
        private readonly MinervaRotationRefresher $refreshService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Date de debut (Y-m-d), timezone America/New_York.')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Date de fin (Y-m-d), timezone America/New_York.')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Horizon en jours si --to absent.', '90')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse sans regeneration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timezone = new DateTimeZone('America/New_York');
        $now = new DateTimeImmutable('now', $timezone);
        $fromOption = $input->getOption('from');
        $toOption = $input->getOption('to');
        $daysOption = $input->getOption('days');

        $from = $this->parseDateStart(is_string($fromOption) ? $fromOption : '', $now, $timezone);
        $to = $this->parseDateEnd(is_string($toOption) ? $toOption : '', $from, $timezone, is_string($daysOption) ? $daysOption : '90');
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $result = $this->refreshService->refresh($from, $to, $dryRun);
        } catch (InvalidArgumentException) {
            $io->error('Plage invalide: --to doit etre superieur ou egal a --from.');

            return Command::INVALID;
        }

        $io->title('Refresh rotation Minerva');
        $io->text(sprintf('Range: %s -> %s (America/New_York)', $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')));
        $io->listing([
            sprintf('Expected windows: %d', $result['expectedWindows']),
            sprintf('Missing windows: %d', $result['missingWindows']),
            sprintf('Covered: %s', $result['covered'] ? 'yes' : 'no'),
            sprintf('Performed: %s', $result['performed'] ? 'yes' : 'no'),
            sprintf('Deleted: %d', $result['deleted']),
            sprintf('Inserted: %d', $result['inserted']),
            sprintf('Skipped: %d', $result['skipped']),
        ]);

        if ($dryRun) {
            $io->success('Dry-run termine.');

            return Command::SUCCESS;
        }

        if ($result['performed']) {
            $io->success('Rotation regeneree pour combler les fenetres manquantes.');
        } else {
            $io->success('Aucune regeneration necessaire.');
        }

        return Command::SUCCESS;
    }

    private function parseDateStart(string $value, DateTimeImmutable $fallbackNow, DateTimeZone $timezone): DateTimeImmutable
    {
        $normalized = trim($value);
        if ('' === $normalized) {
            return $fallbackNow->setTime(0, 0, 0);
        }

        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%s 00:00:00', $normalized), $timezone)
            ?: $fallbackNow->setTime(0, 0, 0);
    }

    private function parseDateEnd(string $value, DateTimeImmutable $from, DateTimeZone $timezone, string $days): DateTimeImmutable
    {
        $normalized = trim($value);
        if ('' !== $normalized) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%s 23:59:59', $normalized), $timezone);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        $horizonDays = max(1, (int) $days);

        return $from
            ->add(new DateInterval(sprintf('P%dD', $horizonDays)))
            ->setTime(23, 59, 59);
    }
}
