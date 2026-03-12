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

    public function testRecognizeFailsWhenApiUrlIsNotAllowed(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'ocr-space-test-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $provider = new OcrSpaceHttpProvider(new MockHttpClient(), 'http://evil.local/parse/image', 'test-key', true, 60);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must target https://api.ocr.space');
        $provider->recognize($imagePath, 'fr');

        @unlink($imagePath);
    }

    public function testRecognizeResizesOversizedImageByReducingDimensions(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required for resize test.');
        }

        $imagePath = tempnam(sys_get_temp_dir(), 'ocr-space-large-');
        self::assertNotFalse($imagePath);

        $image = imagecreatetruecolor(1800, 1800);
        self::assertNotFalse($image);
        $white = imagecolorallocate($image, 255, 255, 255);
        self::assertIsInt($white);
        imagefilledrectangle($image, 0, 0, 1799, 1799, $white);
        imagepng($image, $imagePath);

        $capturedBody = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.ocr.space/parse/image', $url);
            self::assertArrayHasKey('body', $options);
            if (is_string($options['body'])) {
                parse_str($options['body'], $parsed);
                /** @var array<string, string> $parsed */
                $capturedBody = $parsed;
            } else {
                self::assertIsArray($options['body']);
                /** @var array<string, string> $body */
                $body = $options['body'];
                $capturedBody = $body;
            }

            return new MockResponse(json_encode([
                'IsErroredOnProcessing' => false,
                'ParsedResults' => [
                    ['ParsedText' => 'OK'],
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $provider = new OcrSpaceHttpProvider($client, 'https://api.ocr.space/parse/image', 'test-key', true, 60, 10000);
        $provider->recognize($imagePath, 'en');

        self::assertArrayHasKey('base64Image', $capturedBody);
        self::assertArrayHasKey('OCREngine', $capturedBody);
        self::assertSame('2', $capturedBody['OCREngine']);
        self::assertStringStartsWith('data:image/jpeg;base64,', $capturedBody['base64Image']);

        @unlink($imagePath);
    }

    public function testRecognizeUsesConfiguredTimeout(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'ocr-space-timeout-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $capturedTimeout = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedTimeout): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.ocr.space/parse/image', $url);
            $capturedTimeout = $options['timeout'] ?? null;

            return new MockResponse(json_encode([
                'IsErroredOnProcessing' => false,
                'ParsedResults' => [
                    ['ParsedText' => 'OK'],
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $provider = new OcrSpaceHttpProvider($client, 'https://api.ocr.space/parse/image', 'test-key', true, 11);
        $provider->recognize($imagePath, 'en');

        self::assertEquals(11, $capturedTimeout);

        @unlink($imagePath);
    }

    public function testRecognizeUsesOverlayLinesWhenAvailable(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'ocr-space-overlay-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $capturedBody = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.ocr.space/parse/image', $url);
            self::assertArrayHasKey('body', $options);
            if (is_string($options['body'])) {
                parse_str($options['body'], $parsed);
                /** @var array<string, string> $parsed */
                $capturedBody = $parsed;
            } else {
                self::assertIsArray($options['body']);
                /** @var array<string, string> $body */
                $body = $options['body'];
                $capturedBody = $body;
            }

            return new MockResponse(json_encode([
                'IsErroredOnProcessing' => false,
                'ParsedResults' => [
                    [
                        'ParsedText' => "Z fallback\nY fallback",
                        'TextOverlay' => [
                            'Lines' => [
                                [
                                    'LineText' => 'Right line',
                                    'MinTop' => 100,
                                    'Words' => [
                                        ['Left' => 400],
                                    ],
                                ],
                                [
                                    'LineText' => 'Left line',
                                    'MinTop' => 100,
                                    'Words' => [
                                        ['Left' => 120],
                                    ],
                                ],
                                [
                                    'LineText' => 'Top line',
                                    'MinTop' => 20,
                                    'Words' => [
                                        ['Left' => 200],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $provider = new OcrSpaceHttpProvider($client, 'https://api.ocr.space/parse/image', 'test-key', true, 60);
        $result = $provider->recognize($imagePath, 'en');

        self::assertArrayHasKey('isOverlayRequired', $capturedBody);
        self::assertSame('true', $capturedBody['isOverlayRequired']);
        self::assertArrayHasKey('OCREngine', $capturedBody);
        self::assertSame('2', $capturedBody['OCREngine']);
        self::assertArrayHasKey('language', $capturedBody);
        self::assertSame('eng', $capturedBody['language']);
        self::assertSame(['Top line', 'Left line', 'Right line'], $result->lines);

        @unlink($imagePath);
    }

    public function testRecognizeFailsWhenEngineIsInvalid(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'ocr-space-engine-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $provider = new OcrSpaceHttpProvider(new MockHttpClient(), 'https://api.ocr.space/parse/image', 'test-key', true, 60, 950000, 9);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Allowed values: 1, 2');
        $provider->recognize($imagePath, 'fr');

        @unlink($imagePath);
    }

    public function testRecognizeUsesLocaleLanguageForEngineTwo(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'ocr-space-engine2-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'fake-image');

        $capturedBody = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.ocr.space/parse/image', $url);
            self::assertArrayHasKey('body', $options);
            if (is_string($options['body'])) {
                parse_str($options['body'], $parsed);
                /** @var array<string, string> $parsed */
                $capturedBody = $parsed;
            } else {
                self::assertIsArray($options['body']);
                /** @var array<string, string> $body */
                $body = $options['body'];
                $capturedBody = $body;
            }

            return new MockResponse(json_encode([
                'IsErroredOnProcessing' => false,
                'ParsedResults' => [
                    ['ParsedText' => 'OK'],
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $provider = new OcrSpaceHttpProvider($client, 'https://api.ocr.space/parse/image', 'test-key', true, 60, 950000, 2);
        $provider->recognize($imagePath, 'fr');

        self::assertArrayHasKey('OCREngine', $capturedBody);
        self::assertSame('2', $capturedBody['OCREngine']);
        self::assertArrayHasKey('language', $capturedBody);
        self::assertSame('fre', $capturedBody['language']);

        @unlink($imagePath);
    }

    
}
