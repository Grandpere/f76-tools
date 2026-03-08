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

namespace App\Catalog\Application\Roadmap;

final readonly class CreateRoadmapSnapshotInput
{
    public function __construct(
        public string $locale,
        public string $sourceImagePath,
        public string $ocrProvider,
        public float $ocrConfidence,
        public string $rawText,
    ) {
    }
}
