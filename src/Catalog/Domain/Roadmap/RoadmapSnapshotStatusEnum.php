<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Roadmap;

enum RoadmapSnapshotStatusEnum: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}

