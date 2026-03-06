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
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OcrSpaceHttpProvider implements OcrProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly bool $enabled = true,
        private readonly int $timeoutSeconds = 60,
    ) {
    }

    public function name(): string
    {
        return 'ocr.space';
    }

    public function recognize(string $imagePath, string $locale): OcrResult
    {
        if (!$this->enabled) {
            throw new RuntimeException('OCR.space provider is disabled.');
        }

        $apiUrl = trim($this->apiUrl);
        if ('' === $apiUrl) {
            throw new RuntimeException('OCR.space API URL is empty.');
        }

        $apiKey = trim($this->apiKey);
        if ('' === $apiKey) {
            throw new RuntimeException('OCR.space API key is empty.');
        }

        if (!is_file($imagePath)) {
            throw new RuntimeException(sprintf('OCR.space image not found: %s', $imagePath));
        }

        $rawImage = file_get_contents($imagePath);
        if (false === $rawImage) {
            throw new RuntimeException(sprintf('Unable to read image file for OCR.space: %s', $imagePath));
        }

        $language = $this->mapLocaleToOcrSpaceLanguage($locale);
        $mimeType = $this->guessMimeType($imagePath);
        $payload = [
            'base64Image' => sprintf('data:%s;base64,%s', $mimeType, base64_encode($rawImage)),
            'isOverlayRequired' => 'false',
            'language' => $language,
            'OCREngine' => '2',
            'scale' => 'true',
        ];

        try {
            $response = $this->httpClient->request('POST', $apiUrl, [
                'headers' => [
                    'apikey' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $payload,
                'timeout' => $this->timeoutSeconds,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new RuntimeException(sprintf('OCR.space returned HTTP %d.', $response->getStatusCode()));
            }

            /** @var array<string, mixed> $body */
            $body = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new RuntimeException('OCR.space HTTP request failed.', 0, $exception);
        }

        if (($body['IsErroredOnProcessing'] ?? false) === true) {
            $message = $this->extractErrorMessage($body);
            throw new RuntimeException(sprintf('OCR.space rejected image: %s', $message));
        }

        $parsedResults = $body['ParsedResults'] ?? null;
        if (!is_array($parsedResults) || [] === $parsedResults) {
            throw new RuntimeException('OCR.space returned no parsed results.');
        }

        $textBlocks = [];
        foreach ($parsedResults as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $parsedText = $entry['ParsedText'] ?? null;
            if (!is_string($parsedText)) {
                continue;
            }

            $cleanText = trim($parsedText);
            if ('' !== $cleanText) {
                $textBlocks[] = $cleanText;
            }
        }

        if ([] === $textBlocks) {
            throw new RuntimeException('OCR.space returned empty parsed text.');
        }

        $text = implode("\n\n", $textBlocks);
        $lines = $this->normalizeLines($text);
        $confidence = $this->estimateConfidence($text, $lines);

        return new OcrResult($this->name(), $text, $confidence, $lines);
    }

    private function mapLocaleToOcrSpaceLanguage(string $locale): string
    {
        $normalized = strtolower(trim($locale));

        return match (true) {
            str_starts_with($normalized, 'fr') => 'fre',
            str_starts_with($normalized, 'de') => 'ger',
            default => 'eng',
        };
    }

    private function guessMimeType(string $imagePath): string
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * @return list<string>
     */
    private function normalizeLines(string $text): array
    {
        $rawLines = preg_split('/\R/u', $text);
        if (!is_array($rawLines)) {
            return [];
        }

        $lines = [];
        foreach ($rawLines as $line) {
            $clean = trim($line);
            if ('' !== $clean) {
                $lines[] = $clean;
            }
        }

        return $lines;
    }

    /**
     * OCR.space does not expose a confidence score for each extraction.
     * We estimate a bounded score from text richness so quality policy can still rank provider attempts.
     *
     * @param list<string> $lines
     */
    private function estimateConfidence(string $text, array $lines): float
    {
        $lineCount = count($lines);
        $averageLineLength = 0.0;
        if ($lineCount > 0) {
            $totalLength = 0;
            foreach ($lines as $line) {
                $totalLength += mb_strlen($line);
            }
            $averageLineLength = $totalLength / $lineCount;
        }

        $normalizedText = preg_replace('/\s+/u', '', $text);
        $alphaNumericRatio = 0.0;
        if (is_string($normalizedText) && '' !== $normalizedText) {
            $lettersAndDigits = preg_match_all('/[\p{L}\p{N}]/u', $normalizedText);
            if (is_int($lettersAndDigits) && $lettersAndDigits > 0) {
                $alphaNumericRatio = $lettersAndDigits / max(1, mb_strlen($normalizedText));
            }
        }

        $score = 0.50;
        $score += min(0.25, ($lineCount / 20) * 0.25);
        $score += min(0.15, ($averageLineLength / 40) * 0.15);
        $score += min(0.10, $alphaNumericRatio * 0.10);

        return max(0.0, min(0.99, $score));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function extractErrorMessage(array $body): string
    {
        $messages = $body['ErrorMessage'] ?? null;
        if (is_array($messages)) {
            $parts = [];
            foreach ($messages as $message) {
                if (is_string($message) && '' !== trim($message)) {
                    $parts[] = trim($message);
                }
            }

            if ([] !== $parts) {
                return implode('; ', $parts);
            }
        }

        $details = $body['ErrorDetails'] ?? null;
        if (is_string($details) && '' !== trim($details)) {
            return trim($details);
        }

        return 'unknown OCR.space error';
    }
}
