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

use App\Catalog\Application\Roadmap\Ocr\GdImagePreprocessor;
use App\Catalog\Application\Roadmap\Ocr\OcrProviderChain;
use App\Catalog\Application\Roadmap\RoadmapParsedEvent;
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
        private readonly GdImagePreprocessor $gdImagePreprocessor,
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
            ->addOption('preprocess', null, InputOption::VALUE_REQUIRED, 'Preprocess image mode (none|grayscale|bw|strong-bw|layout-bw).', 'layout-bw')
            ->addOption('top', null, InputOption::VALUE_REQUIRED, 'Nombre max d ecarts affiches.', '12')
            ->addOption('preview-lines', null, InputOption::VALUE_REQUIRED, 'Nombre max de lignes OCR par provider.', '8')
            ->addOption('show-all-windows', null, InputOption::VALUE_NONE, 'Affiche toutes les fenetres alignees (pas seulement les ecarts).')
            ->addOption('raw-only', null, InputOption::VALUE_NONE, 'Compare uniquement la qualite OCR brute (sans parser).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $image = $this->normalizeStringInput($input->getArgument('image'));
        $locale = $this->normalizeStringInput($input->getOption('locale'));
        $providerA = strtolower($this->normalizeStringInput($input->getOption('provider-a')));
        $providerB = strtolower($this->normalizeStringInput($input->getOption('provider-b')));
        $preprocessMode = strtolower($this->normalizeStringInput($input->getOption('preprocess')));
        $top = max(1, $this->toPositiveInt($input->getOption('top')));
        $previewLines = max(1, $this->toPositiveInt($input->getOption('preview-lines')));
        $showAllWindows = true === $input->getOption('show-all-windows');
        $rawOnly = true === $input->getOption('raw-only');

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
        if (!in_array($preprocessMode, ['none', 'grayscale', 'bw', 'strong-bw', 'layout-bw'], true)) {
            $io->error(sprintf('Invalid preprocess "%s". Allowed values: none, grayscale, bw, strong-bw, layout-bw.', $preprocessMode));

            return Command::INVALID;
        }

        $referenceDate = new DateTimeImmutable();
        $prepared = ['path' => $image, 'temporary' => false, 'meta' => ['mode' => 'none']];

        try {
            $prepared = $this->gdImagePreprocessor->prepare($image, $preprocessMode);
            $scanA = $this->ocrProviderChain->recognizeWithProvider($prepared['path'], $locale, $providerA);
            $scanB = $this->ocrProviderChain->recognizeWithProvider($prepared['path'], $locale, $providerB);
        } catch (RuntimeException $exception) {
            $io->error('OCR comparison failed: '.$exception->getMessage());

            return Command::FAILURE;
        } finally {
            $this->gdImagePreprocessor->cleanup($prepared['path'], true === $prepared['temporary']);
        }

        $rawA = $this->buildStructuredRawText($scanA->result->lines, $scanA->result->text);
        $rawB = $this->buildStructuredRawText($scanB->result->lines, $scanB->result->text);

        if ($rawOnly) {
            $metricsA = $this->computeRawMetrics($scanA->result->lines, $rawA, $locale);
            $metricsB = $this->computeRawMetrics($scanB->result->lines, $rawB, $locale);

            $io->title('Roadmap OCR providers benchmark (raw-only)');
            $io->definitionList(
                ['Image' => $image],
                ['Locale' => strtoupper($locale)],
                ['Preprocess' => $preprocessMode],
                ['Preprocess details' => $this->formatPreprocessMeta($prepared['meta'])],
                ['Provider A' => $scanA->result->provider],
                ['Provider B' => $scanB->result->provider],
            );

            $summary = new Table($output);
            $summary->setHeaders(['Metric', 'A', 'B']);
            $summary->addRows([
                ['Provider', $scanA->result->provider, $scanB->result->provider],
                ['Confidence', number_format($scanA->result->confidence, 4), number_format($scanB->result->confidence, 4)],
                ['Total lines', (string) count($scanA->result->lines), (string) count($scanB->result->lines)],
                ['Non-empty lines', (string) $metricsA['non_empty_lines'], (string) $metricsB['non_empty_lines']],
                ['Date-like lines', (string) $metricsA['date_like_lines'], (string) $metricsB['date_like_lines']],
                ['Month-like lines', (string) $metricsA['month_like_lines'], (string) $metricsB['month_like_lines']],
                ['Uppercase ratio', sprintf('%.2f', $metricsA['uppercase_ratio']), sprintf('%.2f', $metricsB['uppercase_ratio'])],
                ['Word count', (string) $metricsA['word_count'], (string) $metricsB['word_count']],
            ]);
            $summary->render();

            $this->renderProviderPreview($io, 'A', $scanA->result->provider, $scanA->result->lines, $previewLines);
            $this->renderProviderPreview($io, 'B', $scanB->result->provider, $scanB->result->lines, $previewLines);

            return Command::SUCCESS;
        }

        $eventsA = $this->roadmapRawTextEventParser->parse($rawA, $locale, $referenceDate);
        $eventsB = $this->roadmapRawTextEventParser->parse($rawB, $locale, $referenceDate);
        $validationA = $this->roadmapParsedEventsValidator->validate($eventsA, $locale, $rawA);
        $validationB = $this->roadmapParsedEventsValidator->validate($eventsB, $locale, $rawB);

        $comparison = $this->roadmapTitleComparisonService->compareParsedToParsed($eventsA, $eventsB);

        $io->title('Roadmap OCR providers benchmark');
        $io->definitionList(
            ['Image' => $image],
            ['Locale' => strtoupper($locale)],
            ['Preprocess' => $preprocessMode],
            ['Preprocess details' => $this->formatPreprocessMeta($prepared['meta'])],
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

        $alignmentRows = $this->buildAlignmentRows($eventsA, $eventsB, (string) $comparison['window_mode']);
        if (!$showAllWindows) {
            $alignmentRows = array_values(array_filter(
                $alignmentRows,
                static fn (array $row): bool => in_array($row['status'], ['missing-a', 'missing-b', 'mismatch'], true),
            ));
        }

        if ([] === $alignmentRows) {
            $io->success('No title mismatch detected on matched windows.');

            return Command::SUCCESS;
        }

        $displayRows = array_slice($alignmentRows, 0, $top);

        $table = new Table($output);
        $table->setHeaders(['Window', 'Similarity', 'Status', 'A', 'B']);
        foreach ($displayRows as $row) {
            $table->addRow([
                $row['window'],
                null === $row['similarity'] ? '-' : sprintf('%.1f%%', $row['similarity'] * 100.0),
                $row['status'],
                $row['title_a'],
                $row['title_b'],
            ]);
        }
        $table->render();
        $io->warning(sprintf('Showing %d/%d aligned rows.', count($displayRows), count($alignmentRows)));

        return Command::SUCCESS;
    }

    /**
     * @param list<RoadmapParsedEvent> $eventsA
     * @param list<RoadmapParsedEvent> $eventsB
     *
     * @return list<array{
     *   window:string,
     *   similarity:?float,
     *   status:string,
     *   title_a:string,
     *   title_b:string
     * }>
     */
    private function buildAlignmentRows(array $eventsA, array $eventsB, string $windowMode): array
    {
        /** @var array<string, list<string>> $aByWindow */
        $aByWindow = [];
        /** @var array<string, list<string>> $bByWindow */
        $bByWindow = [];
        /** @var array<string, string> $windowLabelByKey */
        $windowLabelByKey = [];

        foreach ($eventsA as $event) {
            $key = $this->windowKey($event, $windowMode);
            $aByWindow[$key] ??= [];
            $aByWindow[$key][] = trim($event->title);
            $windowLabelByKey[$key] = $this->windowLabel($event);
        }
        foreach ($eventsB as $event) {
            $key = $this->windowKey($event, $windowMode);
            $bByWindow[$key] ??= [];
            $bByWindow[$key][] = trim($event->title);
            $windowLabelByKey[$key] ??= $this->windowLabel($event);
        }

        $allKeys = array_values(array_unique(array_merge(array_keys($aByWindow), array_keys($bByWindow))));
        sort($allKeys);

        $rows = [];
        foreach ($allKeys as $key) {
            $titlesA = $aByWindow[$key] ?? [];
            $titlesB = $bByWindow[$key] ?? [];
            $left = implode(' | ', $titlesA);
            $right = implode(' | ', $titlesB);

            if ([] === $titlesA) {
                $rows[] = [
                    'window' => $windowLabelByKey[$key] ?? $key,
                    'similarity' => null,
                    'status' => 'missing-a',
                    'title_a' => '-',
                    'title_b' => $right,
                ];

                continue;
            }
            if ([] === $titlesB) {
                $rows[] = [
                    'window' => $windowLabelByKey[$key] ?? $key,
                    'similarity' => null,
                    'status' => 'missing-b',
                    'title_a' => $left,
                    'title_b' => '-',
                ];

                continue;
            }

            $bestSimilarity = 0.0;
            $hasExact = false;
            foreach ($titlesA as $titleA) {
                foreach ($titlesB as $titleB) {
                    $similarity = $this->titleSimilarity($titleA, $titleB);
                    if ($similarity > $bestSimilarity) {
                        $bestSimilarity = $similarity;
                    }
                    if ($this->normalizeTitle($titleA) === $this->normalizeTitle($titleB)) {
                        $hasExact = true;
                    }
                }
            }

            $status = $hasExact ? 'exact' : ($bestSimilarity >= 0.70 ? 'similar' : 'mismatch');
            $rows[] = [
                'window' => $windowLabelByKey[$key] ?? $key,
                'similarity' => $bestSimilarity,
                'status' => $status,
                'title_a' => $left,
                'title_b' => $right,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $priority = [
                'missing-a' => 0,
                'missing-b' => 1,
                'mismatch' => 2,
                'similar' => 3,
                'exact' => 4,
            ];
            $leftPriority = $priority[$left['status']];
            $rightPriority = $priority[$right['status']];
            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return strcmp((string) $left['window'], (string) $right['window']);
        });

        return $rows;
    }

    private function windowKey(RoadmapParsedEvent $event, string $mode): string
    {
        if ('month_day' === $mode) {
            return $event->startsAt->format('m-d').'|'.$event->endsAt->format('m-d');
        }

        return $event->startsAt->format('Y-m-d').'|'.$event->endsAt->format('Y-m-d');
    }

    private function windowLabel(RoadmapParsedEvent $event): string
    {
        return $event->startsAt->format('Y-m-d').' -> '.$event->endsAt->format('Y-m-d');
    }

    private function titleSimilarity(string $left, string $right): float
    {
        $leftNormalized = $this->normalizeTitle($left);
        $rightNormalized = $this->normalizeTitle($right);
        if ('' === $leftNormalized && '' === $rightNormalized) {
            return 1.0;
        }
        if ('' === $leftNormalized || '' === $rightNormalized) {
            return 0.0;
        }

        $maxLength = max(strlen($leftNormalized), strlen($rightNormalized));
        $distance = levenshtein($leftNormalized, $rightNormalized);
        $score = 1.0 - ($distance / $maxLength);

        return max(0.0, min(1.0, $score));
    }

    private function normalizeTitle(string $title): string
    {
        $upper = mb_strtoupper(trim($title));
        $ascii = strtr($upper, [
            'À' => 'A',
            'Â' => 'A',
            'Ä' => 'A',
            'Ç' => 'C',
            'É' => 'E',
            'È' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Î' => 'I',
            'Ï' => 'I',
            'Ô' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ÿ' => 'Y',
            'ß' => 'SS',
        ]);
        $lettersOnly = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $ascii) ?? $ascii;
        $singleSpaced = preg_replace('/\s+/u', ' ', $lettersOnly) ?? $lettersOnly;

        return trim($singleSpaced);
    }

    /**
     * @param list<string> $lines
     *
     * @return array{
     *   non_empty_lines:int,
     *   date_like_lines:int,
     *   month_like_lines:int,
     *   uppercase_ratio:float,
     *   word_count:int
     * }
     */
    private function computeRawMetrics(array $lines, string $rawText, string $locale): array
    {
        $nonEmptyLines = 0;
        $dateLikeLines = 0;
        $monthLikeLines = 0;
        foreach ($lines as $line) {
            $candidate = trim($line);
            if ('' === $candidate) {
                continue;
            }
            ++$nonEmptyLines;

            if (1 === preg_match('/\b\d{1,2}\s*(?:-|AU|TO|BIS)\s*\d{1,2}\b/iu', $candidate) || 1 === preg_match('/\b\d{1,2}\b/u', $candidate)) {
                ++$dateLikeLines;
            }

            if ($this->lineContainsMonthToken($candidate, $locale)) {
                ++$monthLikeLines;
            }
        }

        $letters = preg_match_all('/\p{L}/u', $rawText);
        if (!is_int($letters)) {
            $letters = 0;
        }
        $upperLetters = preg_match_all('/\p{Lu}/u', $rawText);
        if (!is_int($upperLetters)) {
            $upperLetters = 0;
        }
        $wordCount = preg_match_all('/\p{L}+/u', $rawText);
        if (!is_int($wordCount)) {
            $wordCount = 0;
        }

        return [
            'non_empty_lines' => $nonEmptyLines,
            'date_like_lines' => $dateLikeLines,
            'month_like_lines' => $monthLikeLines,
            'uppercase_ratio' => $letters > 0 ? ($upperLetters / $letters) : 0.0,
            'word_count' => $wordCount,
        ];
    }

    private function lineContainsMonthToken(string $line, string $locale): bool
    {
        $normalizedLocale = strtolower(trim($locale));

        $tokens = match (true) {
            str_starts_with($normalizedLocale, 'fr') => ['JANVIER', 'FEVRIER', 'MARS', 'AVRIL', 'MAI', 'JUIN', 'JUILLET', 'AOUT', 'SEPTEMBRE', 'OCTOBRE', 'NOVEMBRE', 'DECEMBRE'],
            str_starts_with($normalizedLocale, 'de') => ['JANUAR', 'FEBRUAR', 'MARZ', 'APRIL', 'MAI', 'JUNI', 'JULI', 'AUGUST', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DEZEMBER'],
            default => ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'],
        };

        $upper = mb_strtoupper($line);
        $ascii = strtr($upper, [
            'À' => 'A',
            'Â' => 'A',
            'Ä' => 'A',
            'Ç' => 'C',
            'É' => 'E',
            'È' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Î' => 'I',
            'Ï' => 'I',
            'Ô' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'ß' => 'SS',
        ]);

        foreach ($tokens as $token) {
            if (str_contains($ascii, $token)) {
                return true;
            }
        }

        return false;
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

    /**
     * @param array<string, scalar> $meta
     */
    private function formatPreprocessMeta(array $meta): string
    {
        if ([] === $meta) {
            return 'n/a';
        }

        $parts = [];
        foreach ($meta as $key => $value) {
            $parts[] = sprintf('%s=%s', $key, (string) $value);
        }

        return implode(' | ', $parts);
    }
}
