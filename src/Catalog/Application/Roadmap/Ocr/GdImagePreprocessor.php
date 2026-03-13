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

namespace App\Catalog\Application\Roadmap\Ocr;

use GdImage;
use RuntimeException;

final class GdImagePreprocessor
{
    /**
     * @return array{path:string, temporary:bool}
     */
    public function prepare(string $imagePath, string $mode): array
    {
        $normalizedMode = strtolower(trim($mode));
        if ('' === $normalizedMode || 'none' === $normalizedMode) {
            return ['path' => $imagePath, 'temporary' => false];
        }

        if (!in_array($normalizedMode, ['grayscale', 'bw', 'strong-bw'], true)) {
            throw new RuntimeException(sprintf(
                'Unsupported preprocess mode "%s". Allowed: none, grayscale, bw, strong-bw.',
                $mode,
            ));
        }

        if (
            !function_exists('imagecreatefromstring')
            || !function_exists('imagefilter')
            || !function_exists('imagepng')
        ) {
            throw new RuntimeException('GD image preprocessing is unavailable (missing GD extension functions).');
        }

        $raw = @file_get_contents($imagePath);
        if (false === $raw) {
            throw new RuntimeException(sprintf('Unable to read image for preprocessing: %s', $imagePath));
        }

        $image = @imagecreatefromstring($raw);
        if (!$image instanceof GdImage) {
            throw new RuntimeException(sprintf('Unable to decode image for preprocessing: %s', $imagePath));
        }

        imagefilter($image, IMG_FILTER_GRAYSCALE);

        if ('grayscale' !== $normalizedMode) {
            imagefilter($image, IMG_FILTER_CONTRAST, 'strong-bw' === $normalizedMode ? -45 : -25);
            imagefilter($image, IMG_FILTER_BRIGHTNESS, 'strong-bw' === $normalizedMode ? 8 : 4);
            $this->thresholdToBlackAndWhite($image, 'strong-bw' === $normalizedMode ? 150 : 165);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'roadmap_pre_');
        if (false === $tmp) {
            throw new RuntimeException('Unable to allocate temporary file for preprocessed image.');
        }

        if (!imagepng($image, $tmp)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to write preprocessed image.');
        }

        return ['path' => $tmp, 'temporary' => true];
    }

    public function cleanup(string $path, bool $temporary): void
    {
        if (!$temporary) {
            return;
        }
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function thresholdToBlackAndWhite(GdImage $image, int $threshold): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $white = $this->allocateColor($image, 255, 255, 255);
        $black = $this->allocateColor($image, 0, 0, 0);

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $luminance = (int) round((0.299 * $r) + (0.587 * $g) + (0.114 * $b));
                imagesetpixel($image, $x, $y, $luminance >= $threshold ? $white : $black);
            }
        }
    }

    private function allocateColor(GdImage $image, int $r, int $g, int $b): int
    {
        $red = max(0, min(255, $r));
        $green = max(0, min(255, $g));
        $blue = max(0, min(255, $b));

        $color = imagecolorallocate($image, $red, $green, $blue);
        if (false === $color) {
            throw new RuntimeException('Unable to allocate GD color.');
        }

        return $color;
    }
}
