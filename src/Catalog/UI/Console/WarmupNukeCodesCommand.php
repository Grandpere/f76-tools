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

use App\Catalog\Application\NukeCode\NukeCodeReadApplicationService;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:nuke-codes:warmup',
    description: 'Precharge le cache des codes nucleaires (Nukacrypt).',
)]
final class WarmupNukeCodesCommand extends Command
{
    public function __construct(
        private readonly NukeCodeReadApplicationService $readApplicationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Ignore le cache courant et force un refresh distant.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        try {
            $snapshot = $this->readApplicationService->warmup($force);
        } catch (RuntimeException $exception) {
            $io->error('Warmup nuke codes en echec: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Warmup ok (stale=%s, expiresAt=%s, alpha=%s, bravo=%s, charlie=%s).',
            $snapshot->stale ? 'yes' : 'no',
            $snapshot->expiresAt->format(DATE_ATOM),
            $snapshot->alpha,
            $snapshot->bravo,
            $snapshot->charlie,
        ));

        return Command::SUCCESS;
    }
}
