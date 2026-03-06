<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap;

use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;

interface RoadmapSnapshotWriteRepository
{
    public function save(RoadmapSnapshotEntity $snapshot): void;

    public function findOneById(int $id): ?RoadmapSnapshotEntity;

    /**
     * @return list<RoadmapSnapshotEntity>
     */
    public function findRecent(int $limit = 20): array;
}
