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

use App\Catalog\Application\Roadmap\Ocr\OcrProviderChain;
use App\Catalog\Application\Roadmap\RoadmapParsedEventsValidator;
use App\Catalog\Application\Roadmap\RoadmapRawTextEventParser;
use App\Catalog\Application\Roadmap\RoadmapTitleComparisonService;
use DateTimeImmutable;
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
    name: 'app:roadmap:benchmark-ocr-providers',
    description: 'Compare deux providers OCR sur une meme image roadmap (sans snapshot).',
)]
final class BenchmarkRoadmapOcrProvidersCommand extends Command
{
    public function __construct(
        private readonly OcrProviderChain $ocrProviderChain,
        private readonly RoadmapRawTextEventParser $roadmapRawTextEventParser,
        private readonly RoadmapParsedEventsValidator $roadmapParsedEventsValidator,
        private readonly RoadmapTitleComparisonService $roadmapTitleComparisonService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('image', InputArgument::REQUIRED, 'Chemin de l image a comparer.')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale OCR (fr|en|de).', 'en')
            ->addOption('provider-a', null, InputOption::VALUE_REQUIRED, 'Premier provider OCR.', 'ocr.space')
            ->addOption('provider-b', null, InputOption::VALUE_REQUIRED, 'Second provider OCR.', 'tesseract')
            ->addOption('top', null, InputOption::VALUE_REQUIRED, 'Nombre max d ecarts affiches.', '12')
            ->addOption('preview-lines', null, InputOption::VALUE_REQUIRED, 'Nombre max de lignes OCR par provider.', '8');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $image = $this->normalizeStringInput($input->getArgument('image'));
        $locale = $this->normalizeStringInput($input->getOption('locale'));
        $providerA = strtolower($this->normalizeStringInput($input->getOption('provider-a')));
        $providerB = strtolower($this->normalizeStringInput($input->getOption('provider-b')));
        $top = max(1, $this->toPositiveInt($input->getOption('top')));
        $previewLines = max(1, $this->toPositiveInt($input->getOption('preview-lines')));

        if ('' === $image || !is_file($image)) {
            $io->error(sprintf('Image not found: %s', $image));

            return Command::INVALID;
        }
        if (!$this->isAllowedProvider($providerA) || !$this->isAllowedProvider($providerB)) {
            $io->error('Invalid provider. Allowed values: auto, ocr.space, tesseract.');

            return Command::INVALID;
        }
        if ($providerA === $providerB) {
            $io->error('provider-a and provider-b must be different.');

            return Command::INVALID;
        }

        $referenceDate = new DateTimeImmutable();

        try {
            $scanA = $this->ocrProviderChain->recognizeWithProvider($image, $locale, $providerA);
            $scanB = $this->ocrProviderChain->recognizeWithProvider($image, $locale, $providerB);
        } catch (RuntimeException $exception) {
            $io->error('OCR comparison failed: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $rawA = $this->buildStructuredRawText($scanA->result->lines, $scanA->result->text);
        $rawB = $this->buildStructuredRawText($scanB->result->lines, $scanB->result->text);

        $eventsA = $this->roadmapRawTextEventParser->parse($rawA, $locale, $referenceDate);
        $eventsB = $this->roadmapRawTextEventParser->parse($rawB, $locale, $referenceDate);
        $validationA = $this->roadmapParsedEventsValidator->validate($eventsA, $locale, $rawA);
        $validationB = $this->roadmapParsedEventsValidator->validate($eventsB, $locale, $rawB);

        $comparison = $this->roadmapTitleComparisonService->compareParsedToParsed($eventsA, $eventsB);

        $io->title('Roadmap OCR providers benchmark');
        $io->definitionList(
            ['Image' => $image],
            ['Locale' => strtoupper($locale)],
            ['Provider A' => $scanA->result->provider],
            ['Provider B' => $scanB->result->provider],
            ['Matched windows' => (string) $comparison['matched_windows']],
            ['Window matching mode' => (string) $comparison['window_mode']],
            ['Average similarity' => sprintf('%.2f%%', $comparison['average_similarity'] * 100.0)],
            ['Exact title matches' => (string) $comparison['exact_matches']],
            ['Unmatched windows (A)' => (string) $comparison['unmatched_ocr_windows']],
        );

        $summary = new Table($output);
        $summary->setHeaders(['Metric', 'A', 'B']);
        $summary->addRows([
            ['Provider', $scanA->result->provider, $scanB->result->provider],
            ['Confidence', number_format($scanA->result->confidence, 4), number_format($scanB->result->confidence, 4)],
            ['OCR lines', (string) count($scanA->result->lines), (string) count($scanB->result->lines)],
            ['Parsed events', (string) count($eventsA), (string) count($eventsB)],
            ['Validation errors', (string) count($validationA->errors), (string) count($validationB->errors)],
            ['Validation warnings', (string) count($validationA->warnings), (string) count($validationB->warnings)],
        ]);
        $summary->render();

        $this->renderProviderPreview($io, 'A', $scanA->result->provider, $scanA->result->lines, $previewLines);
        $this->renderProviderPreview($io, 'B', $scanB->result->provider, $scanB->result->lines, $previewLines);

        $mismatches = array_slice($comparison['mismatches'], 0, $top);
        if ([] !== $mismatches) {
            $table = new Table($output);
            $table->setHeaders(['Window', 'Similarity', 'A', 'B']);
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
        } else {
            $io->success('No title mismatch detected on matched windows.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $lines
     */
    private function renderProviderPreview(
        SymfonyStyle $io,
        string $label,
        string $providerName,
        array $lines,
        int $previewLines,
    ): void {
        $io->section(sprintf('Preview %s (%s)', $label, $providerName));
        foreach (array_slice($lines, 0, $previewLines) as $line) {
            $io->writeln('> '.$line);
        }
    }

    private function normalizeStringInput(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
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

    private function isAllowedProvider(string $provider): bool
    {
        return in_array($provider, ['auto', 'ocr.space', 'tesseract'], true);
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
