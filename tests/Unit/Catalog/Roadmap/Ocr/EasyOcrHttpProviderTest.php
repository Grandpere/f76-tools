<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Roadmap\Ocr;

use App\Catalog\Infrastructure\Roadmap\Ocr\EasyOcrHttpProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EasyOcrHttpProviderTest extends TestCase
{
    public function testRecognizeReturnsNormalizedResult(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'provider' => 'easyocr',
                'confidence' => 0.934,
                'text' => "Line A\nLine B",
                'lines' => ['Line A', 'Line B', '  '],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $provider = new EasyOcrHttpProvider($client, 'http://ocr:8001/ocr', true);
        $result = $provider->recognize('/var/www/html/data/roadmap.jpg', 'fr');

        self::assertSame('easyocr', $result->provider);
        self::assertEqualsWithDelta(0.934, $result->confidence, 0.0001);
        self::assertSame(['Line A', 'Line B'], $result->lines);
    }

    public function testRecognizeFailsWhenProviderDisabled(): void
    {
        $provider = new EasyOcrHttpProvider(new MockHttpClient(), 'http://ocr:8001/ocr', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('disabled');

        $provider->recognize('/var/www/html/data/roadmap.jpg', 'en');
    }

    public function testRecognizeFailsWhenHttpStatusIsNotSuccess(): void
    {
        $client = new MockHttpClient([
            new MockResponse('error', ['http_code' => 500]),
        ]);

        $provider = new EasyOcrHttpProvider($client, 'http://ocr:8001/ocr', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        $provider->recognize('/var/www/html/data/roadmap.jpg', 'de');
    }
}
