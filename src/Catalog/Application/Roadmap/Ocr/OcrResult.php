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

final readonly class OcrResult
{
    /**
     * @param list<string> $lines
     */
    public function __construct(
        public string $provider,
        public string $text,
        public float $confidence,
        public array $lines,
    ) {
    }
}
