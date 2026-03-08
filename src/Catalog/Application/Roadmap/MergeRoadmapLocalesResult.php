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
