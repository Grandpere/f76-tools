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

namespace App\Support\UI\Console;

use App\Identity\Application\Security\AuthAuditLogPurger;
use App\Support\Application\Admin\Audit\AdminAuditLogPurger;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:audit:retention:run',
    description: 'Purge les logs d audit admin et auth plus vieux qu un seuil.',
)]
final class RunAuditRetentionCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private readonly AuthAuditLogPurger $authAuditLogPurger,
        private readonly AdminAuditLogPurger $adminAuditLogPurger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Nombre de jours de retention.', (string) self::DEFAULT_RETENTION_DAYS)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche le nombre de lignes purgeables sans supprimer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = $this->normalizeDays($input->getOption('days'), $io);
        if (null === $days) {
            return Command::INVALID;
        }

        $cutoff = new DateTimeImmutable()->sub(new DateInterval(sprintf('P%dD', $days)));
        $isDryRun = (bool) $input->getOption('dry-run');

        if ($isDryRun) {
            $authCount = $this->authAuditLogPurger->countOlderThan($cutoff);
            $adminCount = $this->adminAuditLogPurger->countOlderThan($cutoff);
            $io->success(sprintf(
                'Dry-run: auth=%d, admin=%d, total=%d (seuil: %d jours).',
                $authCount,
                $adminCount,
                $authCount + $adminCount,
                $days,
            ));

            return Command::SUCCESS;
        }

        $authDeleted = $this->authAuditLogPurger->deleteOlderThan($cutoff);
        $adminDeleted = $this->adminAuditLogPurger->deleteOlderThan($cutoff);
        $io->success(sprintf(
            'Purge effectuee: auth=%d, admin=%d, total=%d (seuil: %d jours).',
            $authDeleted,
            $adminDeleted,
            $authDeleted + $adminDeleted,
            $days,
        ));

        return Command::SUCCESS;
    }

    private function normalizeDays(mixed $daysRaw, SymfonyStyle $io): ?int
    {
        if (!is_string($daysRaw) || '' === trim($daysRaw) || !ctype_digit(trim($daysRaw))) {
            $io->error('Option --days invalide (entier positif attendu).');

            return null;
        }

        $days = (int) trim($daysRaw);
        if ($days < 1) {
            $io->error('Option --days invalide (minimum: 1).');

            return null;
        }

        return $days;
    }
}
