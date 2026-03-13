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

use App\Catalog\Application\Roadmap\Ocr\GdImagePreprocessor;
use GdImage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GdImagePreprocessorTest extends TestCase
{
    public function testPrepareReturnsOriginalPathWhenModeIsNone(): void
    {
        $path = $this->createTemporaryPng();
        $preprocessor = new GdImagePreprocessor();

        $prepared = $preprocessor->prepare($path, 'none');

        self::assertSame($path, $prepared['path']);
        self::assertFalse($prepared['temporary']);
        self::assertSame('none', $prepared['meta']['mode'] ?? null);
    }

    public function testPrepareCreatesTemporaryFileForStrongBw(): void
    {
        $path = $this->createTemporaryPng();
        $preprocessor = new GdImagePreprocessor();

        $prepared = $preprocessor->prepare($path, 'strong-bw');

        self::assertTrue($prepared['temporary']);
        self::assertFileExists($prepared['path']);

        $preprocessor->cleanup($prepared['path'], true);
        self::assertFileDoesNotExist($prepared['path']);
    }

    public function testPrepareCreatesTemporaryFileForLayoutBw(): void
    {
        $path = $this->createLargeRoadmapLikePng();
        $preprocessor = new GdImagePreprocessor();

        $prepared = $preprocessor->prepare($path, 'layout-bw');

        self::assertTrue($prepared['temporary']);
        self::assertFileExists($prepared['path']);
        self::assertNotSame($path, $prepared['path']);
        self::assertSame('layout-bw', $prepared['meta']['mode'] ?? null);
        self::assertSame('right-pane>split-4>stack>upscale', $prepared['meta']['layout_strategy'] ?? null);
        $dimensions = @getimagesize($prepared['path']);
        self::assertIsArray($dimensions);
        self::assertArrayHasKey(0, $dimensions);
        self::assertArrayHasKey(1, $dimensions);
        self::assertGreaterThan(450, (int) $dimensions[0]);
        self::assertGreaterThan(300, (int) $dimensions[1]);

        $preprocessor->cleanup($prepared['path'], true);
        self::assertFileDoesNotExist($prepared['path']);
    }

    public function testPrepareThrowsForUnsupportedMode(): void
    {
        $path = $this->createTemporaryPng();
        $preprocessor = new GdImagePreprocessor();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported preprocess mode');

        $preprocessor->prepare($path, 'sepia');
    }

    private function createTemporaryPng(): string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            self::markTestSkipped('GD extension not available.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'gd_pre_');
        if (false === $tmp) {
            self::fail('Unable to create temporary file.');
        }

        $image = imagecreatetruecolor(120, 40);
        if (!$image instanceof GdImage) {
            self::fail('Unable to create temporary GD image.');
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        if (false === $white || false === $black) {
            self::fail('Unable to allocate colors.');
        }

        imagefill($image, 0, 0, $white);
        imagestring($image, 5, 10, 10, 'TEST OCR', $black);
        if (!imagepng($image, $tmp)) {
            self::fail('Unable to write temporary PNG image.');
        }

        return $tmp;
    }

    private function createLargeRoadmapLikePng(): string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            self::markTestSkipped('GD extension not available.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'gd_roadmap_pre_');
        if (false === $tmp) {
            self::fail('Unable to create temporary file.');
        }

        $image = imagecreatetruecolor(480, 300);
        if (!$image instanceof GdImage) {
            self::fail('Unable to create temporary GD image.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 230, 230, 230);
        if (false === $white || false === $black || false === $gray) {
            self::fail('Unable to allocate colors.');
        }

        imagefill($image, 0, 0, $white);
        imagefilledrectangle($image, 0, 0, 120, 300, $gray);
        imagestring($image, 5, 20, 40, 'LEFT PANEL', $black);
        imagestring($image, 5, 150, 30, 'SEPTEMBER', $black);
        imagestring($image, 5, 150, 95, 'OCTOBER', $black);
        imagestring($image, 5, 150, 160, 'NOVEMBER', $black);
        imagestring($image, 5, 150, 225, 'DECEMBER', $black);

        if (!imagepng($image, $tmp)) {
            self::fail('Unable to write temporary PNG image.');
        }

        return $tmp;
    }
}
