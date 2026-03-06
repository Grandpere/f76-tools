<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap;

use RuntimeException;

final readonly class ApproveRoadmapSnapshotApplicationService
{
    public function __construct(
        private RoadmapSnapshotWriteRepository $snapshotWriteRepository,
    ) {
    }

    public function approve(int $snapshotId): void
    {
        $snapshot = $this->snapshotWriteRepository->findOneById($snapshotId);
        if (null === $snapshot) {
            throw new RuntimeException(sprintf('Roadmap snapshot not found: %d', $snapshotId));
        }

        $snapshot->approve(null);
        $this->snapshotWriteRepository->save($snapshot);
    }
}

