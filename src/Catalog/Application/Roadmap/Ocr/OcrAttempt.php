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

final readonly class OcrAttempt
{
    /**
     * @param list<string> $qualityReasons
     */
    public function __construct(
        public string $provider,
        public bool $successful,
        public ?float $confidence,
        public bool $acceptable,
        public array $qualityReasons,
        public ?string $error = null,
    ) {
    }
}
