<?php

declare(strict_types=1);

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

