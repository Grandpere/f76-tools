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

namespace App\Identity\UI\Console;

use App\Identity\Application\Security\AuthAuditLogPurger;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:auth:audit:purge',
    description: 'Supprime les logs d authentification plus vieux qu un seuil.',
)]
final class PurgeAuthAuditLogsCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private readonly AuthAuditLogPurger $authAuditLogPurger,
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

        $daysRaw = $input->getOption('days');
        if (!is_string($daysRaw) || '' === trim($daysRaw) || !ctype_digit(trim($daysRaw))) {
            $io->error('Option --days invalide (entier positif attendu).');

            return Command::INVALID;
        }

        $days = (int) trim($daysRaw);
        if ($days < 1) {
            $io->error('Option --days invalide (minimum: 1).');

            return Command::INVALID;
        }

        $cutoff = new DateTimeImmutable()->sub(new DateInterval(sprintf('P%dD', $days)));
        $isDryRun = (bool) $input->getOption('dry-run');

        if ($isDryRun) {
            $count = $this->authAuditLogPurger->countOlderThan($cutoff);
            $io->success(sprintf('Dry-run: %d log(s) auth seraient supprimes (seuil: %d jours).', $count, $days));

            return Command::SUCCESS;
        }

        $deleted = $this->authAuditLogPurger->deleteOlderThan($cutoff);
        $io->success(sprintf('%d log(s) auth supprimes (seuil: %d jours).', $deleted, $days));

        return Command::SUCCESS;
    }
}
