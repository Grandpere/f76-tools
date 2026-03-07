<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap;

use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;

interface RoadmapCanonicalEventWriteRepository
{
    public function clearAll(): void;

    /**
     * @param list<RoadmapCanonicalEventEntity> $events
     */
    public function saveAll(array $events): void;
}

