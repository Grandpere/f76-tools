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
    private const PREPROCESS_MODES = ['grayscale', 'bw', 'strong-bw', 'layout-bw'];

    /**
     * @return array{path:string, temporary:bool, meta:array<string, scalar>}
     */
    public function prepare(string $imagePath, string $mode): array
    {
        $normalizedMode = strtolower(trim($mode));
        $inputSize = @getimagesize($imagePath);
        $inputWidth = is_array($inputSize) ? (int) $inputSize[0] : 0;
        $inputHeight = is_array($inputSize) ? (int) $inputSize[1] : 0;

        if ('' === $normalizedMode || 'none' === $normalizedMode) {
            return [
                'path' => $imagePath,
                'temporary' => false,
                'meta' => [
                    'mode' => 'none',
                    'input_width' => $inputWidth,
                    'input_height' => $inputHeight,
                    'output_width' => $inputWidth,
                    'output_height' => $inputHeight,
                ],
            ];
        }

        if (!in_array($normalizedMode, self::PREPROCESS_MODES, true)) {
            throw new RuntimeException(sprintf('Unsupported preprocess mode "%s". Allowed: none, %s.', $mode, implode(', ', self::PREPROCESS_MODES)));
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

        if ('layout-bw' === $normalizedMode) {
            $image = $this->prepareLayoutBwImage($image);
        } else {
            imagefilter($image, IMG_FILTER_GRAYSCALE);

            if ('grayscale' !== $normalizedMode) {
                imagefilter($image, IMG_FILTER_CONTRAST, 'strong-bw' === $normalizedMode ? -45 : -25);
                imagefilter($image, IMG_FILTER_BRIGHTNESS, 'strong-bw' === $normalizedMode ? 8 : 4);
                $this->thresholdToBlackAndWhite($image, 'strong-bw' === $normalizedMode ? 150 : 165);
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'roadmap_pre_');
        if (false === $tmp) {
            throw new RuntimeException('Unable to allocate temporary file for preprocessed image.');
        }

        if (!imagepng($image, $tmp)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to write preprocessed image.');
        }

        $outputWidth = imagesx($image);
        $outputHeight = imagesy($image);
        $meta = [
            'mode' => $normalizedMode,
            'input_width' => $inputWidth,
            'input_height' => $inputHeight,
            'output_width' => $outputWidth,
            'output_height' => $outputHeight,
        ];

        if ('layout-bw' === $normalizedMode) {
            $cropX = (int) round($inputWidth * 0.26);
            $cropY = (int) round($inputHeight * 0.07);
            $meta['layout_strategy'] = 'right-pane>split-4>stack>upscale';
            $meta['layout_crop_x'] = $cropX;
            $meta['layout_crop_y'] = $cropY;
            $meta['layout_crop_width'] = max(1, $inputWidth - $cropX);
            $meta['layout_crop_height'] = max(1, (int) round($inputHeight * 0.9));
            $meta['layout_band_count'] = 4;
            $meta['layout_upscale_factor'] = 1.9;
        }

        return ['path' => $tmp, 'temporary' => true, 'meta' => $meta];
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

    private function prepareLayoutBwImage(GdImage $image): GdImage
    {
        $cropped = $this->cropToRightRoadmapPane($image);
        $image = $cropped;
        $image = $this->splitAndStackMonthlyBands($image);

        $upscaled = $this->upscaleImage($image, 1.9);
        $image = $upscaled;

        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, -52);
        imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);
        $this->thresholdToBlackAndWhite($image, 152);

        return $image;
    }

    private function cropToRightRoadmapPane(GdImage $image): GdImage
    {
        if (!function_exists('imagecrop')) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 200 || $height < 120) {
            return $image;
        }

        $x = (int) round($width * 0.26);
        $y = (int) round($height * 0.07);
        $cropWidth = max(1, $width - $x);
        $cropHeight = max(1, (int) round($height * 0.9));
        if ($x + $cropWidth > $width) {
            $cropWidth = $width - $x;
        }
        if ($y + $cropHeight > $height) {
            $cropHeight = $height - $y;
        }

        $cropped = @imagecrop($image, [
            'x' => $x,
            'y' => $y,
            'width' => $cropWidth,
            'height' => $cropHeight,
        ]);

        return $cropped instanceof GdImage ? $cropped : $image;
    }

    private function splitAndStackMonthlyBands(GdImage $image): GdImage
    {
        if (!function_exists('imagecrop') || !function_exists('imagecopy')) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 200 || $height < 200) {
            return $image;
        }

        $topOffset = (int) round($height * 0.04);
        $bottomOffset = (int) round($height * 0.03);
        $usableHeight = $height - $topOffset - $bottomOffset;
        if ($usableHeight < 120) {
            return $image;
        }

        $bandCount = 4;
        $baseBandHeight = (int) floor($usableHeight / $bandCount);
        if ($baseBandHeight < 28) {
            return $image;
        }

        $overlap = (int) round($baseBandHeight * 0.07);
        $bands = [];

        for ($index = 0; $index < $bandCount; ++$index) {
            $bandY = $topOffset + ($index * $baseBandHeight) - $overlap;
            $bandHeight = $baseBandHeight + (2 * $overlap);
            if ($index === $bandCount - 1) {
                $bandHeight = ($height - $bottomOffset) - $bandY;
            }

            $bandY = max(0, $bandY);
            if ($bandY + $bandHeight > $height) {
                $bandHeight = $height - $bandY;
            }
            if ($bandHeight < 16) {
                continue;
            }

            $band = @imagecrop($image, [
                'x' => 0,
                'y' => $bandY,
                'width' => $width,
                'height' => $bandHeight,
            ]);

            if ($band instanceof GdImage) {
                $bands[] = $band;
            }
        }

        if ([] === $bands) {
            return $image;
        }

        $gap = 12;
        $targetHeight = ($gap * (count($bands) - 1));
        foreach ($bands as $band) {
            $targetHeight += imagesy($band);
        }

        $canvas = imagecreatetruecolor($width, $targetHeight);
        if (!$canvas instanceof GdImage) {
            return $image;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        if (false === $white) {
            return $image;
        }
        imagefill($canvas, 0, 0, $white);

        $offsetY = 0;
        foreach ($bands as $band) {
            imagecopy($canvas, $band, 0, $offsetY, 0, 0, imagesx($band), imagesy($band));
            $offsetY += imagesy($band) + $gap;
        }

        return $canvas;
    }

    private function upscaleImage(GdImage $image, float $factor): GdImage
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) {
            return $image;
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $targetWidth = max(1, (int) round($sourceWidth * $factor));
        $targetHeight = max(1, (int) round($sourceHeight * $factor));
        if ($targetWidth === $sourceWidth && $targetHeight === $sourceHeight) {
            return $image;
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$canvas instanceof GdImage) {
            return $image;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        if (false === $white) {
            return $image;
        }
        imagefill($canvas, 0, 0, $white);

        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        return $canvas;
    }
}
