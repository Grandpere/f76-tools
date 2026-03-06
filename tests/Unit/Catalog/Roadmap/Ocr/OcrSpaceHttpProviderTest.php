<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Roadmap\Ocr;

use App\Catalog\Infrastructure\Roadmap\Ocr\OcrSpaceHttpProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OcrSpaceHttpProviderTest extends TestCase
{
    public function testRecognizeReturnsNormalizedResult(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'ocr-space-test-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'IsErroredOnProcessing' => false,
                'ParsedResults' => [
                    [
                        'ParsedText' => "Line A\nLine B",
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $provider = new OcrSpaceHttpProvider($client, 'https://api.ocr.space/parse/image', 'test-key', true, 60);
        $result = $provider->recognize($imagePath, 'fr');

        self::assertSame('ocr.space', $result->provider);
        self::assertGreaterThan(0.0, $result->confidence);
        self::assertSame(['Line A', 'Line B'], $result->lines);

        @unlink($imagePath);
    }

    public function testRecognizeFailsWhenProviderDisabled(): void
    {
        $provider = new OcrSpaceHttpProvider(new MockHttpClient(), 'https://api.ocr.space/parse/image', 'test-key', false, 60);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('disabled');

        $provider->recognize('/tmp/missing-file.jpg', 'en');
    }

    public function testRecognizeFailsWhenApiReturnsError(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'ocr-space-test-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'IsErroredOnProcessing' => true,
                'ErrorMessage' => ['Bad image quality'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $provider = new OcrSpaceHttpProvider($client, 'https://api.ocr.space/parse/image', 'test-key', true, 60);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bad image quality');
        $provider->recognize($imagePath, 'de');

        @unlink($imagePath);
    }
}

