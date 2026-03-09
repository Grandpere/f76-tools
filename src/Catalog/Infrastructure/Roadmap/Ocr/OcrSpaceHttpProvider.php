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
use GdImage;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OcrSpaceHttpProvider implements OcrProvider
{
    private const ALLOWED_HOST = 'api.ocr.space';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly bool $enabled = true,
        private readonly int $timeoutSeconds = 60,
        private readonly int $maxImageBytes = 950000,
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
        $this->assertApiUrlAllowed($apiUrl);

        $apiKey = trim($this->apiKey);
        if ('' === $apiKey) {
            throw new RuntimeException('OCR.space API key is empty.');
        }

        if (!is_file($imagePath)) {
            throw new RuntimeException(sprintf('OCR.space image not found: %s', $imagePath));
        }

        $preparedImage = $this->prepareImageForUpload($imagePath);
        $rawImage = $preparedImage['content'];
        $language = $this->mapLocaleToOcrSpaceLanguage($locale);
        $mimeType = $preparedImage['mimeType'];
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

    /**
     * @return array{content: string, mimeType: string}
     */
    private function prepareImageForUpload(string $imagePath): array
    {
        $rawImage = file_get_contents($imagePath);
        if (!is_string($rawImage) || '' === $rawImage) {
            throw new RuntimeException(sprintf('Unable to read image file for OCR.space: %s', $imagePath));
        }

        $mimeType = $this->guessMimeType($imagePath);
        if (strlen($rawImage) <= $this->maxImageBytes) {
            return ['content' => $rawImage, 'mimeType' => $mimeType];
        }

        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Image exceeds OCR.space limit and GD is not available for resizing.');
        }

        $source = @imagecreatefromstring($rawImage);
        if (!$source instanceof GdImage) {
            throw new RuntimeException('Image exceeds OCR.space limit and could not be decoded for resizing.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = 1.0;
        $attempt = 0;
        $currentBytes = '';
        while ($attempt < 10) {
            ++$attempt;

            $targetWidth = max(1, (int) floor($sourceWidth * $scale));
            $targetHeight = max(1, (int) floor($sourceHeight * $scale));

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$canvas instanceof GdImage) {
                break;
            }

            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

            ob_start();
            imagejpeg($canvas, null, 92);
            $encoded = ob_get_clean();

            if (!is_string($encoded) || '' === $encoded) {
                break;
            }

            $currentBytes = $encoded;
            if (strlen($encoded) <= $this->maxImageBytes) {
                return [
                    'content' => $encoded,
                    'mimeType' => 'image/jpeg',
                ];
            }

            $scale *= 0.88;
        }

        if ('' !== $currentBytes) {
            throw new RuntimeException(sprintf('Unable to fit image under OCR.space size limit (%d bytes) after resize attempts.', $this->maxImageBytes));
        }

        throw new RuntimeException('Image resize failed before OCR.space upload.');
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

    private function assertApiUrlAllowed(string $apiUrl): void
    {
        $parts = parse_url($apiUrl);
        if (!is_array($parts)) {
            throw new RuntimeException('OCR.space API URL is invalid.');
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        if ('https' !== $scheme || self::ALLOWED_HOST !== $host) {
            throw new RuntimeException('OCR.space API URL must target https://api.ocr.space.');
        }
    }
}
