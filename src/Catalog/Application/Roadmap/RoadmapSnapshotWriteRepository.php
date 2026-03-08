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

use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;

interface RoadmapSnapshotWriteRepository
{
    public function save(RoadmapSnapshotEntity $snapshot): void;

    public function delete(RoadmapSnapshotEntity $snapshot): void;

    public function findOneById(int $id): ?RoadmapSnapshotEntity;

    /**
     * @return list<RoadmapSnapshotEntity>
     */
    public function findRecent(int $limit = 20): array;
}
