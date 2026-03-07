<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap;

use DateTimeImmutable;

final readonly class RoadmapParsedEvent
{
    public function __construct(
        public string $title,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
    }
}
