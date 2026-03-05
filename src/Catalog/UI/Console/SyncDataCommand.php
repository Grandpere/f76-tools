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

use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:data:sync',
    description: 'Synchronise les fichiers JSON legendary mods et Minerva depuis Nukaknights.',
)]
final class SyncDataCommand extends Command
{
    private const BASE_URL = 'https://nukaknights.com/ajax/home.html';
    private const MAX_ATTEMPTS = 3;
    private const BASE_BACKOFF_MS = 500;
    private const MIN_DELAY_BETWEEN_REQUESTS_MS = 150;
    private const MAX_DELAY_BETWEEN_REQUESTS_MS = 400;

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Data sync (Nukaknights)');

        $projectDir = rtrim($this->kernel->getProjectDir(), '/');
        $httpClient = HttpClient::create([
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'f76-data-sync-experimentation/1.0 (+https://github.com/Grandpere/f76-tools)',
            ],
        ]);

        $errors = [];
        $updated = 0;

        foreach (range(1, 4) as $legendaryRank) {
            $url = self::BASE_URL.'?legendary_mods='.$legendaryRank.'&lang=en';
            $target = sprintf('%s/data/legendary_mods_pages/legendary_mods_%d_en.json', $projectDir, $legendaryRank);

            if ($this->syncFile($httpClient, $url, $target, $errors, $io)) {
                ++$updated;
            }
        }

        foreach (range(61, 84) as $minervaList) {
            $url = self::BASE_URL.'?minerva='.$minervaList.'&lang=en';
            $target = sprintf('%s/data/minerva_pages/minerva_%d_en.json', $projectDir, $minervaList);

            if ($this->syncFile($httpClient, $url, $target, $errors, $io)) {
                ++$updated;
            }
        }

        $io->newLine();
        $io->definitionList(
            ['Updated files' => (string) $updated],
            ['Errors' => (string) count($errors)],
        );

        if ([] !== $errors) {
            foreach ($errors as $error) {
                $io->error($error);
            }

            return Command::FAILURE;
        }

        $io->success('Sync terminee.');

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $errors
     */
    private function syncFile(HttpClientInterface $httpClient, string $url, string $target, array &$errors, SymfonyStyle $io): bool
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $this->naturalDelay();

            try {
                $response = $httpClient->request('GET', $url);
                $statusCode = $response->getStatusCode();
                if (200 !== $statusCode) {
                    if ($attempt < self::MAX_ATTEMPTS && $this->isRetryableStatus($statusCode)) {
                        $this->backoff($attempt);
                        continue;
                    }

                    $errors[] = sprintf('HTTP %d sur %s', $statusCode, $url);

                    return false;
                }

                $content = $response->getContent();
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded) || !array_is_list($decoded)) {
                    $errors[] = sprintf('Payload invalide (liste JSON attendue): %s', $url);

                    return false;
                }

                if (false === file_put_contents($target, $content)) {
                    $errors[] = sprintf('Ecriture impossible: %s', $target);

                    return false;
                }

                $io->text(sprintf('Updated: %s', str_replace($this->kernel->getProjectDir().'/', '', $target)));

                return true;
            } catch (ExceptionInterface|JsonException $e) {
                if ($attempt < self::MAX_ATTEMPTS) {
                    $this->backoff($attempt);
                    continue;
                }

                $errors[] = sprintf('%s (%s)', $e->getMessage(), $url);

                return false;
            }
        }

        return false;
    }

    private function isRetryableStatus(int $statusCode): bool
    {
        return 429 === $statusCode || $statusCode >= 500;
    }

    private function backoff(int $attempt): void
    {
        $multiplier = 1 << ($attempt - 1);
        $delayMs = self::BASE_BACKOFF_MS * $multiplier;
        usleep($delayMs * 1000);
    }

    private function naturalDelay(): void
    {
        $delayMs = random_int(self::MIN_DELAY_BETWEEN_REQUESTS_MS, self::MAX_DELAY_BETWEEN_REQUESTS_MS);
        usleep($delayMs * 1000);
    }
}
