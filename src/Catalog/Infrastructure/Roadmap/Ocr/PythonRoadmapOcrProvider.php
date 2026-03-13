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
use App\Catalog\Application\Roadmap\Ocr\OcrProviderUnavailableException;
use App\Catalog\Application\Roadmap\Ocr\OcrResult;
use JsonException;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PythonRoadmapOcrProvider implements OcrProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly bool $enabled = false,
        private readonly string $baseUrl = '',
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function name(): string
    {
        return 'python.ocr';
    }

    public function recognize(string $imagePath, string $locale): OcrResult
    {
        if (!$this->enabled) {
            throw new OcrProviderUnavailableException($this->name(), 'provider disabled by configuration.');
        }

        if (!is_file($imagePath)) {
            throw new RuntimeException(sprintf('Python OCR image not found: %s', $imagePath));
        }

        $endpoint = $this->buildEndpoint();

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'timeout' => max(1, $this->timeoutSeconds),
                'body' => [
                    'locale' => strtolower(trim($locale)),
                    'image' => fopen($imagePath, 'rb'),
                ],
            ]);
            $statusCode = $response->getStatusCode();
            $rawBody = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            throw new OcrProviderUnavailableException($this->name(), 'python OCR service unavailable: '.$exception->getMessage());
        }

        if ($statusCode >= 500) {
            throw new OcrProviderUnavailableException($this->name(), sprintf('python OCR service returned %d.', $statusCode));
        }

        if ($statusCode >= 400) {
            throw new RuntimeException(sprintf('python OCR request rejected with status %d.', $statusCode));
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('python OCR response is not valid JSON.', 0, $exception);
        }

        if (!is_array($payload)) {
            throw new RuntimeException('python OCR response payload is invalid.');
        }

        $lines = $this->extractLines($payload['lines'] ?? null);
        $text = $this->extractText($payload['text'] ?? null, $lines);
        if ('' === trim($text)) {
            throw new RuntimeException('python OCR returned empty text.');
        }

        $provider = $this->name();
        $payloadProvider = $payload['provider'] ?? null;
        if (is_string($payloadProvider) && '' !== trim($payloadProvider)) {
            $provider = trim($payloadProvider);
        }

        $confidence = 0.0;
        $payloadConfidence = $payload['confidence'] ?? null;
        if (is_numeric($payloadConfidence)) {
            $confidence = max(0.0, min(1.0, (float) $payloadConfidence));
        }

        return new OcrResult($provider, $text, $confidence, $lines);
    }

    private function buildEndpoint(): string
    {
        $base = trim($this->baseUrl);
        if ('' === $base) {
            throw new OcrProviderUnavailableException($this->name(), 'python OCR base URL is empty.');
        }

        return rtrim($base, '/').'/ocr/roadmap/scan';
    }

    /**
     * @return list<string>
     */
    private function extractLines(mixed $linesPayload): array
    {
        if (!is_array($linesPayload)) {
            return [];
        }

        $lines = [];
        foreach ($linesPayload as $line) {
            if (!is_string($line)) {
                continue;
            }
            $clean = trim($line);
            if ('' !== $clean) {
                $lines[] = $clean;
            }
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function extractText(mixed $textPayload, array $lines): string
    {
        if (is_string($textPayload) && '' !== trim($textPayload)) {
            return trim($textPayload);
        }

        if ([] === $lines) {
            return '';
        }

        return implode("\n", $lines);
    }
}
