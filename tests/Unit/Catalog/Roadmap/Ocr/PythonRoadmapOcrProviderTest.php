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

namespace App\Tests\Unit\Catalog\Roadmap\Ocr;

use App\Catalog\Application\Roadmap\Ocr\OcrProviderUnavailableException;
use App\Catalog\Infrastructure\Roadmap\Ocr\PythonRoadmapOcrProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PythonRoadmapOcrProviderTest extends TestCase
{
    public function testRecognizeThrowsUnavailableWhenDisabled(): void
    {
        $provider = new PythonRoadmapOcrProvider(new MockHttpClient(), false, 'http://roadmap-ocr:8081', 5);

        $this->expectException(OcrProviderUnavailableException::class);
        $this->expectExceptionMessage('disabled');

        $provider->recognize('/tmp/missing-image.png', 'fr');
    }

    public function testRecognizeReturnsNormalizedResult(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'python-ocr-test-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'provider' => 'python.ocr',
                'confidence' => 0.9345,
                'text' => "Line A\nLine B",
                'lines' => ['Line A', 'Line B'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $provider = new PythonRoadmapOcrProvider($client, true, 'http://roadmap-ocr:8081', 5);
        $result = $provider->recognize($imagePath, 'en');

        self::assertSame('python.ocr', $result->provider);
        self::assertSame(['Line A', 'Line B'], $result->lines);
        self::assertSame("Line A\nLine B", $result->text);
        self::assertGreaterThan(0.9, $result->confidence);

        @unlink($imagePath);
    }

    public function testRecognizeTreatsServerErrorAsUnavailable(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'python-ocr-test-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $client = new MockHttpClient([
            new MockResponse('{"error":"down"}', ['http_code' => 503]),
        ]);

        $provider = new PythonRoadmapOcrProvider($client, true, 'http://roadmap-ocr:8081', 5);

        $this->expectException(OcrProviderUnavailableException::class);
        $this->expectExceptionMessage('returned 503');
        $provider->recognize($imagePath, 'en');

        @unlink($imagePath);
    }

    public function testRecognizeThrowsWhenResponseIsInvalid(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'python-ocr-test-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $client = new MockHttpClient([
            new MockResponse('not-json'),
        ]);

        $provider = new PythonRoadmapOcrProvider($client, true, 'http://roadmap-ocr:8081', 5);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not valid JSON');
        $provider->recognize($imagePath, 'en');

        @unlink($imagePath);
    }
}
