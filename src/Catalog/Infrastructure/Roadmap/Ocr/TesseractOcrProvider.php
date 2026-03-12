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
    private const BASE_PSM_PASSES = [6, 4];
    /** @var list<int> */
    private const RESCUE_PSM_PASSES = [11, 7];

    public function __construct(
        private readonly CommandRunner $commandRunner,
        private readonly string $binaryPath = 'tesseract',
        private readonly int $timeoutSeconds = 45,
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
        [$imageWidth, $imageHeight] = $this->resolveImageDimensions($image);
        $passes = [];
        $errors = [];

        foreach (self::BASE_PSM_PASSES as $psm) {
            $this->runPass($image, $lang, $psm, $imageWidth, $imageHeight, $passes, $errors);
        }

        if ($this->shouldRunRescuePasses($passes)) {
            foreach (self::RESCUE_PSM_PASSES as $psm) {
                $this->runPass($image, $lang, $psm, $imageWidth, $imageHeight, $passes, $errors);
            }
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

    /**
     * @param list<array{psm:int, lines:list<string>, confidence:float, score:float}> $passes
     * @param list<string> $errors
     */
    private function runPass(
        string $imagePath,
        string $lang,
        int $psm,
        ?int $imageWidth,
        ?int $imageHeight,
        array &$passes,
        array &$errors,
    ): void {
        $tsvResult = $this->commandRunner->run([
            $this->binaryPath,
            $imagePath,
            'stdout',
            '-l',
            $lang,
            '--psm',
            (string) $psm,
            'tsv',
        ], $this->timeoutSeconds);

        if (!$tsvResult->isSuccessful()) {
            $errors[] = sprintf('psm=%d exit=%d stderr=%s', $psm, $tsvResult->exitCode, trim($tsvResult->stderr));

            return;
        }

        [$rawLines, $confidence] = $this->extractParagraphLinesAndConfidenceFromTsv(
            $tsvResult->stdout,
            $imageWidth,
            $imageHeight,
        );
        $lines = $this->filterLinesByTextQuality($rawLines);
        $passes[] = [
            'psm' => $psm,
            'lines' => $lines,
            'confidence' => $confidence,
            'score' => $this->scorePass($confidence, $lines),
        ];
    }

    /**
     * @param list<array{psm:int, lines:list<string>, confidence:float, score:float}> $passes
     */
    private function shouldRunRescuePasses(array $passes): bool
    {
        if ([] === $passes) {
            return true;
        }

        $bestConfidence = 0.0;
        foreach ($passes as $pass) {
            if ($pass['confidence'] > $bestConfidence) {
                $bestConfidence = $pass['confidence'];
            }
        }

        return $bestConfidence < 0.75;
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

    /**
     * @param list<string> $lines
     */
    private function scorePass(float $confidence, array $lines): float
    {
        $lineCount = count($lines);
        $lineBonus = min(50, max(0, $lineCount)) / 100.0;
        $qualitySum = 0.0;
        foreach ($lines as $line) {
            $qualitySum += $this->lineTextQualityScore($line);
        }
        $avgQuality = $lineCount > 0 ? ($qualitySum / $lineCount) : 0.0;

        return ($confidence * 10.0) + $lineBonus + ($avgQuality * 2.0);
    }

    /**
     * @return array{0: list<string>, 1: float}
     */
    private function extractParagraphLinesAndConfidenceFromTsv(string $tsv, ?int $imageWidth = null, ?int $imageHeight = null): array
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
            $word = trim((string) $columns[11]);
            if ('' === $word) {
                continue;
            }

            $left = is_numeric($columns[6]) ? (int) $columns[6] : 0;
            $top = is_numeric($columns[7]) ? (int) $columns[7] : 0;
            $width = is_numeric($columns[8]) ? (int) $columns[8] : 0;
            $height = is_numeric($columns[9]) ? (int) $columns[9] : 0;
            if (!$this->shouldKeepWordBox($word, $left, $top, $width, $height, $confRaw, $imageWidth, $imageHeight)) {
                continue;
            }
            if (is_numeric($confRaw)) {
                $conf = (float) $confRaw;
                if ($conf >= 0.0) {
                    $sum += $conf;
                    ++$count;
                }
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

    private function shouldKeepWordBox(
        string $word,
        int $left,
        int $top,
        int $width,
        int $height,
        string $confRaw,
        ?int $imageWidth,
        ?int $imageHeight,
    ): bool {
        if ('' === trim($word)) {
            return false;
        }

        if (!preg_match('/\p{L}|\d/u', $word)) {
            return false;
        }

        if (is_numeric($confRaw) && ((float) $confRaw) >= 0.0 && ((float) $confRaw) < 20.0) {
            return false;
        }

        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $ratio = $width / max(1, $height);
        if ($ratio > 22.0 || $ratio < 0.08) {
            return false;
        }

        $minWidth = 4;
        $minHeight = 4;
        $minArea = 20;
        if (is_int($imageWidth) && $imageWidth > 0 && is_int($imageHeight) && $imageHeight > 0) {
            $minWidth = max($minWidth, (int) round($imageWidth * 0.006));
            $minHeight = max($minHeight, (int) round($imageHeight * 0.004));
            $minArea = max($minArea, (int) round(($imageWidth * $imageHeight) * 0.00003));
        }

        $area = $width * $height;
        if ($width < $minWidth || $height < $minHeight || $area < $minArea) {
            return false;
        }

        if (is_int($imageWidth) && $imageWidth > 0 && is_int($imageHeight) && $imageHeight > 0) {
            $margin = max(2, (int) round(min($imageWidth, $imageHeight) * 0.003));
            $right = $left + $width;
            $bottom = $top + $height;
            $isOnBorder = $left <= $margin || $top <= $margin || $right >= ($imageWidth - $margin) || $bottom >= ($imageHeight - $margin);
            if ($isOnBorder && $area < ($minArea * 2)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveImageDimensions(string $imagePath): array
    {
        $imageSize = @getimagesize($imagePath);
        if (!is_array($imageSize)) {
            return [null, null];
        }

        $width = $imageSize[0];
        $height = $imageSize[1];

        return [$width, $height];
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function filterLinesByTextQuality(array $lines): array
    {
        $filtered = [];
        foreach ($lines as $line) {
            $candidate = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
            if ('' === $candidate) {
                continue;
            }
            if ($this->lineTextQualityScore($candidate) < 0.35) {
                continue;
            }

            $filtered[] = $candidate;
        }

        return $filtered;
    }

    private function lineTextQualityScore(string $line): float
    {
        $length = mb_strlen($line);
        if ($length <= 0) {
            return 0.0;
        }

        $alphaCount = preg_match_all('/\p{L}|\d/u', $line);
        if (!is_int($alphaCount)) {
            $alphaCount = 0;
        }
        $symbolCount = preg_match_all('/[^\p{L}\d\s]/u', $line);
        if (!is_int($symbolCount)) {
            $symbolCount = 0;
        }

        if ($alphaCount < 2) {
            return 0.0;
        }

        if (1 === preg_match('/^[\W_]+$/u', $line)) {
            return 0.0;
        }

        if (1 === preg_match('/([|:_\.\-])\1{2,}/u', $line)) {
            return 0.1;
        }

        $alphaRatio = $alphaCount / max(1, $length);
        $symbolRatio = $symbolCount / max(1, $length);
        $wordCount = preg_match_all('/\p{L}+/u', $line);
        if (!is_int($wordCount)) {
            $wordCount = 0;
        }

        $score = 0.0;
        $score += min(1.0, $alphaRatio * 1.8) * 0.6;
        $score += (1.0 - min(1.0, $symbolRatio * 2.5)) * 0.2;
        $score += min(1.0, $wordCount / 6.0) * 0.2;

        return max(0.0, min(1.0, $score));
    }
}
