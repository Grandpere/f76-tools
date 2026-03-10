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

use RuntimeException;

final class OcrProviderUnavailableException extends RuntimeException
{
    public function __construct(
        private readonly string $providerName,
        string $message = 'OCR provider is unavailable.',
    ) {
        parent::__construct($message);
    }

    public function providerName(): string
    {
        return $this->providerName;
    }
}

