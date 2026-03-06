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

final class EasyOcrHttpProvider implements OcrProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $ocrUrl,
        private readonly bool $enabled = true,
    ) {
    }

    public function name(): string
    {
        return 'easyocr';
    }

    public function recognize(string $imagePath, string $locale): OcrResult
    {
        if (!$this->enabled) {
            throw new RuntimeException('EasyOCR provider is disabled.');
        }

        $url = trim($this->ocrUrl);
        if ('' === $url) {
            throw new RuntimeException('EasyOCR URL is empty.');
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'image_path' => $imagePath,
                    'locale' => $locale,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new RuntimeException(sprintf('EasyOCR returned HTTP %d.', $response->getStatusCode()));
            }

            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new RuntimeException('EasyOCR HTTP request failed.', 0, $exception);
        }

        $provider = isset($payload['provider']) && is_string($payload['provider']) ? trim($payload['provider']) : $this->name();
        $text = isset($payload['text']) && is_string($payload['text']) ? trim($payload['text']) : '';
        $confidence = isset($payload['confidence']) && is_numeric($payload['confidence']) ? (float) $payload['confidence'] : 0.0;

        $linesRaw = $payload['lines'] ?? [];
        $lines = [];
        if (is_array($linesRaw)) {
            foreach ($linesRaw as $line) {
                if (!is_string($line)) {
                    continue;
                }
                $clean = trim($line);
                if ('' !== $clean) {
                    $lines[] = $clean;
                }
            }
        }

        return new OcrResult(
            '' === $provider ? $this->name() : $provider,
            $text,
            max(0.0, min(1.0, $confidence)),
            $lines,
        );
    }
}
