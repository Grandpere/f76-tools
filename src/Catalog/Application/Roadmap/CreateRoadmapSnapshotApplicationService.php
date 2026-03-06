<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap;

use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use App\Catalog\Domain\Roadmap\RoadmapSnapshotStatusEnum;
use RuntimeException;

final readonly class CreateRoadmapSnapshotApplicationService
{
    public function __construct(
        private RoadmapSnapshotWriteRepository $snapshotWriteRepository,
    ) {
    }

    public function create(CreateRoadmapSnapshotInput $input): RoadmapSnapshotEntity
    {
        $hash = hash_file('sha256', $input->sourceImagePath);
        if (!is_string($hash)) {
            throw new RuntimeException(sprintf('Unable to compute roadmap image hash: %s', $input->sourceImagePath));
        }

        $snapshot = (new RoadmapSnapshotEntity())
            ->setLocale($input->locale)
            ->setSourceImagePath($input->sourceImagePath)
            ->setSourceImageHash($hash)
            ->setOcrProvider($input->ocrProvider)
            ->setOcrConfidence($input->ocrConfidence)
            ->setRawText($input->rawText)
            ->setStatus(RoadmapSnapshotStatusEnum::DRAFT);

        $this->snapshotWriteRepository->save($snapshot);

        return $snapshot;
    }
}
