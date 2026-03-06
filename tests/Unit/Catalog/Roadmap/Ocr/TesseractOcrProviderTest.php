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

use App\Catalog\Infrastructure\Roadmap\Ocr\CommandExecutionResult;
use App\Catalog\Infrastructure\Roadmap\Ocr\CommandRunner;
use App\Catalog\Infrastructure\Roadmap\Ocr\TesseractOcrProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TesseractOcrProviderTest extends TestCase
{
    public function testRecognizeReturnsTextLinesAndAverageConfidence(): void
    {
        $imagePath = $this->createTemporaryImage();

        $runner = new FakeCommandRunner([
            new CommandExecutionResult(0, "Event One\nEvent Two\n", ''),
            new CommandExecutionResult(0, "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext\n5\t1\t1\t1\t1\t1\t0\t0\t10\t10\t96\tEvent\n5\t1\t1\t1\t1\t2\t0\t0\t10\t10\t84\tOne\n", ''),
        ]);

        $provider = new TesseractOcrProvider($runner, 'tesseract');
        $result = $provider->recognize($imagePath, 'en');

        self::assertSame('tesseract', $result->provider);
        self::assertSame(['Event One', 'Event Two'], $result->lines);
        self::assertEqualsWithDelta(0.90, $result->confidence, 0.0001);
        self::assertCount(2, $runner->commands);
    }

    public function testRecognizeThrowsWhenImageDoesNotExist(): void
    {
        $provider = new TesseractOcrProvider(new FakeCommandRunner([]), 'tesseract');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('image not found');

        $provider->recognize('/tmp/non-existing-roadmap-file.png', 'fr');
    }

    public function testRecognizeThrowsWhenTesseractCommandFails(): void
    {
        $imagePath = $this->createTemporaryImage();

        $runner = new FakeCommandRunner([
            new CommandExecutionResult(1, '', 'missing language data'),
        ]);

        $provider = new TesseractOcrProvider($runner, 'tesseract');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tesseract text extraction failed');

        $provider->recognize($imagePath, 'de');
    }

    private function createTemporaryImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'roadmap_ocr_test_');
        if (false === $path) {
            self::fail('Unable to create temporary file.');
        }

        file_put_contents($path, 'fake image content');

        return $path;
    }
}

final class FakeCommandRunner implements CommandRunner
{
    /** @var list<CommandExecutionResult> */
    private array $results;

    /** @var list<list<string>> */
    public array $commands = [];

    /** @param list<CommandExecutionResult> $results */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function run(array $command, int $timeoutSeconds = 30): CommandExecutionResult
    {
        $this->commands[] = $command;
        $result = array_shift($this->results);
        if (!$result instanceof CommandExecutionResult) {
            throw new RuntimeException('No fake command result configured.');
        }

        return $result;
    }
}
