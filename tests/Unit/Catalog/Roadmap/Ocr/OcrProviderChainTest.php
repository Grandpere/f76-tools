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

use App\Catalog\Application\Roadmap\Ocr\OcrProvider;
use App\Catalog\Application\Roadmap\Ocr\OcrProviderChain;
use App\Catalog\Application\Roadmap\Ocr\OcrProviderUnavailableException;
use App\Catalog\Application\Roadmap\Ocr\OcrQualityAssessor;
use App\Catalog\Application\Roadmap\Ocr\OcrQualityPolicy;
use App\Catalog\Application\Roadmap\Ocr\OcrResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OcrProviderChainTest extends TestCase
{
    public function testUsesFirstProviderWhenQualityIsAcceptable(): void
    {
        $chain = new OcrProviderChain([
            new FakeProvider('tesseract', new OcrResult('tesseract', 'Line A\nLine B\nLine C', 0.95, ['Line A', 'Line B', 'Line C'])),
            new FakeProvider('paddle', new OcrResult('paddle', 'Unused', 0.99, ['Unused'])),
        ]);

        $result = $chain->recognize('/tmp/roadmap.png', 'en');

        self::assertSame('tesseract', $result->result->provider);
        self::assertFalse($result->usedFallback);
        self::assertCount(1, $result->attempts);
        self::assertTrue($result->attempts[0]->acceptable);
    }

    public function testFallsBackWhenFirstProviderQualityIsTooLow(): void
    {
        $chain = new OcrProviderChain(
            [
                new FakeProvider('tesseract', new OcrResult('tesseract', 'Too weak', 0.62, ['One line'])),
                new FakeProvider('paddle', new OcrResult('paddle', 'Good\nEnough\nResult', 0.96, ['Good', 'Enough', 'Result'])),
            ],
            new OcrQualityAssessor(new OcrQualityPolicy(0.90, 3)),
        );

        $result = $chain->recognize('/tmp/roadmap.png', 'fr');

        self::assertSame('paddle', $result->result->provider);
        self::assertTrue($result->usedFallback);
        self::assertCount(2, $result->attempts);
        self::assertFalse($result->attempts[0]->acceptable);
        self::assertTrue($result->attempts[1]->acceptable);
    }

    public function testContinuesWhenProviderThrows(): void
    {
        $chain = new OcrProviderChain([
            new ThrowingProvider('tesseract', 'binary not available'),
            new FakeProvider('paddle', new OcrResult('paddle', 'Good\nEnough\nResult', 0.93, ['Good', 'Enough', 'Result'])),
        ]);

        $result = $chain->recognize('/tmp/roadmap.png', 'de');

        self::assertSame('paddle', $result->result->provider);
        self::assertTrue($result->usedFallback);
        self::assertCount(2, $result->attempts);
        self::assertFalse($result->attempts[0]->successful);
        self::assertSame('binary not available', $result->attempts[0]->error);
    }

    public function testKeepsBestConfidenceWhenNoProviderMeetsThreshold(): void
    {
        $chain = new OcrProviderChain(
            [
                new FakeProvider('ocr.space', new OcrResult('ocr.space', 'Result A', 0.8978, ['Result A'])),
                new FakeProvider('tesseract', new OcrResult('tesseract', 'Result B', 0.8338, ['Result B'])),
            ],
            new OcrQualityAssessor(new OcrQualityPolicy(0.90, 1)),
        );

        $result = $chain->recognize('/tmp/roadmap.png', 'fr');

        self::assertSame('ocr.space', $result->result->provider);
        self::assertTrue($result->usedFallback);
        self::assertCount(2, $result->attempts);
        self::assertFalse($result->attempts[0]->acceptable);
        self::assertFalse($result->attempts[1]->acceptable);
    }

    public function testSkipsUnavailableProviderFromAttempts(): void
    {
        $chain = new OcrProviderChain([
            new UnavailableProvider('paddle', 'not installed'),
            new FakeProvider('tesseract', new OcrResult('tesseract', 'Good', 0.91, ['Good'])),
        ]);

        $result = $chain->recognize('/tmp/roadmap.png', 'fr');

        self::assertSame('tesseract', $result->result->provider);
        self::assertCount(1, $result->attempts);
        self::assertSame('tesseract', $result->attempts[0]->provider);
    }

    public function testThrowsWhenNoProviderConfigured(): void
    {
        $chain = new OcrProviderChain([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No OCR provider configured.');

        $chain->recognize('/tmp/roadmap.png', 'en');
    }
}

final readonly class FakeProvider implements OcrProvider
{
    public function __construct(
        private string $name,
        private OcrResult $result,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function recognize(string $imagePath, string $locale): OcrResult
    {
        return $this->result;
    }
}

final readonly class ThrowingProvider implements OcrProvider
{
    public function __construct(
        private string $name,
        private string $message,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function recognize(string $imagePath, string $locale): OcrResult
    {
        throw new RuntimeException($this->message);
    }
}

final readonly class UnavailableProvider implements OcrProvider
{
    public function __construct(
        private string $name,
        private string $message,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function recognize(string $imagePath, string $locale): OcrResult
    {
        throw new OcrProviderUnavailableException($this->name, $this->message);
    }
}
