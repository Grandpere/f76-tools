<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap;

final readonly class MergeRoadmapLocalesResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public int $totalEvents,
        public int $highConfidenceEvents,
        public int $mediumConfidenceEvents,
        public int $lowConfidenceEvents,
        public array $warnings = [],
    ) {
    }
}

