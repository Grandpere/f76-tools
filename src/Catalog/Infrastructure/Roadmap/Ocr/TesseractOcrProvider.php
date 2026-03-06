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

        $textResult = $this->commandRunner->run([
            $this->binaryPath,
            $image,
            'stdout',
            '-l',
            $lang,
            '--psm',
            '6',
        ], 45);
        if (!$textResult->isSuccessful()) {
            throw new RuntimeException(sprintf('Tesseract text extraction failed (exit=%d): %s', $textResult->exitCode, trim($textResult->stderr)));
        }

        $tsvResult = $this->commandRunner->run([
            $this->binaryPath,
            $image,
            'stdout',
            '-l',
            $lang,
            '--psm',
            '6',
            'tsv',
        ], 45);
        if (!$tsvResult->isSuccessful()) {
            throw new RuntimeException(sprintf('Tesseract confidence extraction failed (exit=%d): %s', $tsvResult->exitCode, trim($tsvResult->stderr)));
        }

        $text = trim($textResult->stdout);
        $lines = $this->extractNonEmptyLines($text);
        $confidence = $this->extractAverageConfidenceFromTsv($tsvResult->stdout);

        return new OcrResult(
            $this->name(),
            $text,
            $confidence,
            $lines,
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

    /**
     * @return list<string>
     */
    private function extractNonEmptyLines(string $text): array
    {
        if ('' === trim($text)) {
            return [];
        }

        $lines = preg_split('/\R/u', $text);
        if (!is_array($lines)) {
            return [];
        }

        $normalized = [];
        foreach ($lines as $line) {
            $clean = trim($line);
            if ('' !== $clean) {
                $normalized[] = $clean;
            }
        }

        return $normalized;
    }

    private function extractAverageConfidenceFromTsv(string $tsv): float
    {
        $lines = preg_split('/\R/u', trim($tsv));
        if (!is_array($lines) || count($lines) < 2) {
            return 0.0;
        }

        $sum = 0.0;
        $count = 0;

        foreach ($lines as $index => $line) {
            if (0 === $index) {
                continue;
            }

            $columns = explode("\t", $line);
            if (count($columns) < 11) {
                continue;
            }

            $conf = trim((string) $columns[10]);
            if (!is_numeric($conf)) {
                continue;
            }

            $confFloat = (float) $conf;
            if ($confFloat < 0.0) {
                continue;
            }

            $sum += $confFloat;
            ++$count;
        }

        if (0 === $count) {
            return 0.0;
        }

        $normalized = ($sum / $count) / 100.0;
        if ($normalized < 0.0) {
            return 0.0;
        }
        if ($normalized > 1.0) {
            return 1.0;
        }

        return $normalized;
    }
}
