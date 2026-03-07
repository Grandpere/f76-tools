<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap;

use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;

interface RoadmapCanonicalEventReadRepository
{
    /**
     * @return list<RoadmapCanonicalEventEntity>
     */
    public function findAllOrdered(): array;
}

