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

namespace App\Catalog\Infrastructure\Roadmap\Ocr;

use App\Catalog\Application\Roadmap\Ocr\OcrProvider;
use App\Catalog\Application\Roadmap\Ocr\OcrProviderUnavailableException;
use App\Catalog\Application\Roadmap\Ocr\OcrResult;

final class UnavailablePaddleOcrProvider implements OcrProvider
{
    public function name(): string
    {
        return 'paddle';
    }

    public function recognize(string $imagePath, string $locale): OcrResult
    {
        throw new OcrProviderUnavailableException(
            $this->name(),
            'Paddle OCR provider is not installed/configured yet.',
        );
    }
}
