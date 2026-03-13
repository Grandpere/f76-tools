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

use App\Catalog\Application\Roadmap\CreateRoadmapSnapshotApplicationService;
use App\Catalog\Application\Roadmap\CreateRoadmapSnapshotInput;
use App\Catalog\Application\Roadmap\Ocr\GdImagePreprocessor;
use App\Catalog\Application\Roadmap\Ocr\OcrProviderChain;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roadmap:ocr:scan',
    description: 'Scanne une image roadmap avec la chaine OCR configuree et affiche le resultat.',
)]
final class ScanRoadmapImageCommand extends Command
{
    public function __construct(
        private readonly OcrProviderChain $ocrProviderChain,
        private readonly CreateRoadmapSnapshotApplicationService $createRoadmapSnapshotApplicationService,
        private readonly GdImagePreprocessor $gdImagePreprocessor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('image', InputArgument::REQUIRED, 'Chemin de l image a scanner.')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale OCR (fr|en|de).', 'en')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Provider OCR (auto|ocr.space|tesseract).', 'auto')
            ->addOption('preprocess', null, InputOption::VALUE_REQUIRED, 'Preprocess image mode (none|grayscale|bw|strong-bw).', 'none')
            ->addOption('preview-lines', null, InputOption::VALUE_REQUIRED, 'Nombre max de lignes affichees.', '20')
            ->addOption('no-persist', null, InputOption::VALUE_NONE, 'N enregistre pas le snapshot OCR en base.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $image = $this->normalizeStringInput($input->getArgument('image'));
        $locale = $this->normalizeStringInput($input->getOption('locale'));
        $provider = strtolower($this->normalizeStringInput($input->getOption('provider')));
        $preprocessMode = strtolower($this->normalizeStringInput($input->getOption('preprocess')));
        $previewLinesRaw = $this->normalizeStringInput($input->getOption('preview-lines'));
        $previewLines = ctype_digit($previewLinesRaw) ? max(1, (int) $previewLinesRaw) : 20;
        $persist = !$input->getOption('no-persist');

        if ('' === $image || !is_file($image)) {
            $io->error(sprintf('Image not found: %s', $image));

            return Command::INVALID;
        }
        if (!in_array($provider, ['auto', 'ocr.space', 'tesseract'], true)) {
            $io->error(sprintf('Invalid provider "%s". Allowed values: auto, ocr.space, tesseract.', $provider));

            return Command::INVALID;
        }
        if (!in_array($preprocessMode, ['none', 'grayscale', 'bw', 'strong-bw'], true)) {
            $io->error(sprintf('Invalid preprocess "%s". Allowed values: none, grayscale, bw, strong-bw.', $preprocessMode));

            return Command::INVALID;
        }

        $prepared = ['path' => $image, 'temporary' => false];
        try {
            $prepared = $this->gdImagePreprocessor->prepare($image, $preprocessMode);
            $scan = $this->ocrProviderChain->recognizeWithProvider($prepared['path'], $locale, $provider);
        } catch (RuntimeException $exception) {
            $io->error('OCR scan failed: '.$exception->getMessage());

            return Command::FAILURE;
        } finally {
            $this->gdImagePreprocessor->cleanup($prepared['path'], true === $prepared['temporary']);
        }

        $io->title('Roadmap OCR scan');
        $io->definitionList(
            ['Requested provider' => $provider],
            ['Preprocess' => $preprocessMode],
            ['Provider' => $scan->result->provider],
            ['Confidence' => number_format($scan->result->confidence, 4)],
            ['Used fallback' => $scan->usedFallback ? 'yes' : 'no'],
            ['Lines' => (string) count($scan->result->lines)],
        );

        $io->section('Attempts');
        foreach ($scan->attempts as $attempt) {
            $line = sprintf(
                '- %s | success=%s | acceptable=%s',
                $attempt->provider,
                $attempt->successful ? 'yes' : 'no',
                $attempt->acceptable ? 'yes' : 'no',
            );

            if (null !== $attempt->confidence) {
                $line .= sprintf(' | confidence=%.4f', $attempt->confidence);
            }
            if ([] !== $attempt->qualityReasons) {
                $line .= ' | reasons='.implode('; ', $attempt->qualityReasons);
            }
            if (null !== $attempt->error) {
                $line .= ' | error='.$attempt->error;
            }

            $io->writeln($line);
        }

        $io->section('Text preview');
        foreach (array_slice($scan->result->lines, 0, $previewLines) as $line) {
            $io->writeln('> '.$line);
        }

        if ($persist) {
            try {
                $snapshot = $this->createRoadmapSnapshotApplicationService->create(
                    new CreateRoadmapSnapshotInput(
                        $locale,
                        $image,
                        $scan->result->provider,
                        $scan->result->confidence,
                        $this->buildStructuredRawText($scan->result->lines, $scan->result->text),
                    ),
                );

                $io->newLine();
                $io->success(sprintf(
                    'Roadmap snapshot persisted (id=%d, status=%s).',
                    $snapshot->getId() ?? 0,
                    $snapshot->getStatus()->value,
                ));
                if (null === $snapshot->getSeason()) {
                    $io->warning('No season marker detected in OCR text. Snapshot saved without season.');
                } else {
                    $io->text(sprintf('Detected season: %d', $snapshot->getSeason()->getSeasonNumber()));
                }
            } catch (RuntimeException $exception) {
                $io->warning('OCR succeeded but snapshot persistence failed: '.$exception->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    private function normalizeStringInput(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param list<string> $lines
     */
    private function buildStructuredRawText(array $lines, string $fallbackText): string
    {
        $normalizedLines = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ), static fn (string $line): bool => '' !== $line));

        if ([] !== $normalizedLines) {
            return implode("\n", $normalizedLines);
        }

        return $fallbackText;
    }
}
