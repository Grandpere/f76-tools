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
    public function testRecognizeRebuildsParagraphAndAverageConfidenceFromTsv(): void
    {
        $imagePath = $this->createTemporaryImage();

        $runner = new FakeCommandRunner([
            new CommandExecutionResult(0, $this->tsvWithWrappedParagraph(82), ''),
            new CommandExecutionResult(0, $this->tsvSingleLine('Ignored fallback pass', 70), ''),
        ]);

        $provider = new TesseractOcrProvider($runner, 'tesseract');
        $result = $provider->recognize($imagePath, 'fr');

        self::assertSame('tesseract', $result->provider);
        self::assertSame([
            'DOUBLE SCORE, DOUBLES MUTATIONS ET CAPSULES A GOGO',
        ], $result->lines);
        self::assertEqualsWithDelta(0.82, $result->confidence, 0.0001);
        self::assertCount(2, $runner->commands);
    }

    public function testRecognizeSelectsBestPassByScore(): void
    {
        $imagePath = $this->createTemporaryImage();

        $runner = new FakeCommandRunner([
            new CommandExecutionResult(0, $this->tsvSingleLine('LOW CONFIDENCE PASS', 40), ''),
            new CommandExecutionResult(0, $this->tsvSingleLine('BETTER PASS', 92), ''),
        ]);

        $provider = new TesseractOcrProvider($runner, 'tesseract');
        $result = $provider->recognize($imagePath, 'en');

        self::assertSame(['BETTER PASS'], $result->lines);
        self::assertEqualsWithDelta(0.92, $result->confidence, 0.0001);
    }

    public function testRecognizeThrowsWhenImageDoesNotExist(): void
    {
        $provider = new TesseractOcrProvider(new FakeCommandRunner([]), 'tesseract');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('image not found');

        $provider->recognize('/tmp/non-existing-roadmap-file.png', 'fr');
    }

    public function testRecognizeThrowsWhenAllTesseractPassesFail(): void
    {
        $imagePath = $this->createTemporaryImage();

        $runner = new FakeCommandRunner([
            new CommandExecutionResult(1, '', 'missing language data'),
            new CommandExecutionResult(1, '', 'missing language data'),
        ]);

        $provider = new TesseractOcrProvider($runner, 'tesseract');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tesseract extraction failed');

        $provider->recognize($imagePath, 'de');
    }

    public function testRecognizeFiltersLowConfidenceAndNonAlphanumericNoiseWords(): void
    {
        $imagePath = $this->createTemporaryImage();

        $runner = new FakeCommandRunner([
            new CommandExecutionResult(0, $this->tsvWithNoiseAndValidWord(), ''),
            new CommandExecutionResult(0, $this->tsvSingleLine('fallback pass', 10), ''),
        ]);

        $provider = new TesseractOcrProvider($runner, 'tesseract');
        $result = $provider->recognize($imagePath, 'fr');

        self::assertSame(['MISE A JOUR'], $result->lines);
        self::assertGreaterThan(0.80, $result->confidence);
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

    private function tsvWithWrappedParagraph(float $confidence): string
    {
        return "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext\n"
            ."5\t1\t1\t1\t1\t1\t0\t0\t10\t10\t{$confidence}\tDOUBLE\n"
            ."5\t1\t1\t1\t1\t2\t0\t0\t10\t10\t{$confidence}\tSCORE,\n"
            ."5\t1\t1\t1\t2\t1\t0\t0\t10\t10\t{$confidence}\tDOUBLES\n"
            ."5\t1\t1\t1\t2\t2\t0\t0\t10\t10\t{$confidence}\tMUTATIONS\n"
            ."5\t1\t1\t1\t2\t3\t0\t0\t10\t10\t{$confidence}\tET\n"
            ."5\t1\t1\t1\t2\t4\t0\t0\t10\t10\t{$confidence}\tCAPSULES\n"
            ."5\t1\t1\t1\t2\t5\t0\t0\t10\t10\t{$confidence}\tA\n"
            ."5\t1\t1\t1\t2\t6\t0\t0\t10\t10\t{$confidence}\tGOGO\n";
    }

    private function tsvSingleLine(string $text, float $confidence): string
    {
        $words = preg_split('/\s+/u', trim($text));
        if (!is_array($words)) {
            $words = [$text];
        }

        $rows = ["level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext"];
        $wordIndex = 1;
        foreach ($words as $word) {
            if ('' === trim($word)) {
                continue;
            }
            $rows[] = sprintf("5\t1\t1\t1\t1\t%d\t0\t0\t10\t10\t%.2f\t%s", $wordIndex, $confidence, $word);
            ++$wordIndex;
        }

        return implode("\n", $rows)."\n";
    }

    private function tsvWithNoiseAndValidWord(): string
    {
        return "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext\n"
            ."5\t1\t1\t1\t1\t1\t0\t0\t10\t10\t5\t###\n"
            ."5\t1\t1\t1\t1\t2\t0\t0\t10\t10\t10\tNOISE\n"
            ."5\t1\t1\t1\t1\t3\t0\t0\t10\t10\t87\tMISE\n"
            ."5\t1\t1\t1\t1\t4\t0\t0\t10\t10\t87\tA\n"
            ."5\t1\t1\t1\t1\t5\t0\t0\t10\t10\t87\tJOUR\n";
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
