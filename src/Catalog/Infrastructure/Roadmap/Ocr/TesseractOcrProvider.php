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

namespace App\Catalog\Infrastructure\Roadmap\Ocr;

use App\Catalog\Application\Roadmap\Ocr\OcrProvider;
use App\Catalog\Application\Roadmap\Ocr\OcrResult;
use RuntimeException;

final class TesseractOcrProvider implements OcrProvider
{
    /** @var list<int> */
    private const PSM_PASSES = [6, 4];

    public function __construct(
        private readonly CommandRunner $commandRunner,
        private readonly string $binaryPath = 'tesseract',
    ) {
    }

    public function name(): string
    {
        return 'tesseract';
    }

    public function recognize(string $imagePath, string $locale): OcrResult
    {
        $image = trim($imagePath);
        if ('' === $image) {
            throw new RuntimeException('Tesseract image path is empty.');
        }
        if (!is_file($image)) {
            throw new RuntimeException(sprintf('Tesseract image not found: %s', $image));
        }

        $lang = $this->mapLocaleToTesseractLang($locale);
        $passes = [];
        $errors = [];

        foreach (self::PSM_PASSES as $psm) {
            $tsvResult = $this->commandRunner->run([
                $this->binaryPath,
                $image,
                'stdout',
                '-l',
                $lang,
                '--psm',
                (string) $psm,
                'tsv',
            ], 45);

            if (!$tsvResult->isSuccessful()) {
                $errors[] = sprintf('psm=%d exit=%d stderr=%s', $psm, $tsvResult->exitCode, trim($tsvResult->stderr));
                continue;
            }

            [$lines, $confidence] = $this->extractParagraphLinesAndConfidenceFromTsv($tsvResult->stdout);
            $passes[] = [
                'psm' => $psm,
                'lines' => $lines,
                'confidence' => $confidence,
                'score' => $this->scorePass($confidence, count($lines)),
            ];
        }

        if ([] === $passes) {
            $details = [] === $errors ? 'unknown tesseract failure' : implode(' | ', $errors);
            throw new RuntimeException(sprintf('Tesseract extraction failed: %s', $details));
        }

        usort(
            $passes,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score'],
        );

        /** @var array{psm:int, lines:list<string>, confidence:float, score:float} $best */
        $best = $passes[0];
        $text = implode("\n", $best['lines']);

        return new OcrResult(
            $this->name(),
            $text,
            $best['confidence'],
            $best['lines'],
        );
    }

    private function mapLocaleToTesseractLang(string $locale): string
    {
        $normalized = strtolower(trim($locale));

        return match (true) {
            str_starts_with($normalized, 'fr') => 'fra',
            str_starts_with($normalized, 'de') => 'deu',
            default => 'eng',
        };
    }

    private function scorePass(float $confidence, int $lineCount): float
    {
        $lineBonus = min(50, max(0, $lineCount)) / 100.0;

        return ($confidence * 10.0) + $lineBonus;
    }

    /**
     * @return array{0: list<string>, 1: float}
     */
    private function extractParagraphLinesAndConfidenceFromTsv(string $tsv): array
    {
        $rows = preg_split('/\R/u', trim($tsv));
        if (!is_array($rows) || count($rows) < 2) {
            return [[], 0.0];
        }

        /**
         * @var array<string, array{
         *   page:int,
         *   block:int,
         *   paragraph:int,
         *   lines: array<int, array<int, string>>
         * }> $paragraphs
         */
        $paragraphs = [];

        $sum = 0.0;
        $count = 0;

        foreach ($rows as $index => $row) {
            if (0 === $index) {
                continue;
            }

            $columns = explode("\t", $row);
            if (count($columns) < 12) {
                continue;
            }

            $confRaw = trim((string) $columns[10]);
            if (is_numeric($confRaw)) {
                $conf = (float) $confRaw;
                if ($conf >= 0.0) {
                    $sum += $conf;
                    ++$count;
                }
            }

            $word = trim((string) $columns[11]);
            if ('' === $word) {
                continue;
            }

            $page = is_numeric($columns[1]) ? (int) $columns[1] : 0;
            $block = is_numeric($columns[2]) ? (int) $columns[2] : 0;
            $paragraph = is_numeric($columns[3]) ? (int) $columns[3] : 0;
            $line = is_numeric($columns[4]) ? (int) $columns[4] : 0;
            $wordIndex = is_numeric($columns[5]) ? (int) $columns[5] : 0;

            $paragraphKey = sprintf('%06d:%06d:%06d', $page, $block, $paragraph);
            if (!isset($paragraphs[$paragraphKey])) {
                $paragraphs[$paragraphKey] = [
                    'page' => $page,
                    'block' => $block,
                    'paragraph' => $paragraph,
                    'lines' => [],
                ];
            }

            if (!isset($paragraphs[$paragraphKey]['lines'][$line])) {
                $paragraphs[$paragraphKey]['lines'][$line] = [];
            }

            $paragraphs[$paragraphKey]['lines'][$line][$wordIndex] = $word;
        }

        ksort($paragraphs);

        $resultLines = [];
        foreach ($paragraphs as $paragraphData) {
            $lineTexts = [];
            $lineMap = $paragraphData['lines'];
            ksort($lineMap);

            foreach ($lineMap as $wordsByOrder) {
                ksort($wordsByOrder);
                $lineText = trim(implode(' ', $wordsByOrder));
                if ('' !== $lineText) {
                    $lineTexts[] = $lineText;
                }
            }

            if ([] === $lineTexts) {
                continue;
            }

            $paragraphText = preg_replace('/\s+/u', ' ', trim(implode(' ', $lineTexts)));
            if (!is_string($paragraphText) || '' === $paragraphText) {
                continue;
            }

            $resultLines[] = $paragraphText;
        }

        if (0 === $count) {
            return [$resultLines, 0.0];
        }

        $normalizedConfidence = ($sum / $count) / 100.0;

        return [$resultLines, max(0.0, min(1.0, $normalizedConfidence))];
    }
}
