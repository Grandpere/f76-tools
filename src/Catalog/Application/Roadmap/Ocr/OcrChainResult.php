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

final readonly class OcrChainResult
{
    /**
     * @param list<OcrAttempt> $attempts
     */
    public function __construct(
        public OcrResult $result,
        public bool $usedFallback,
        public array $attempts,
    ) {
    }
}
