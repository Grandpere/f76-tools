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

use App\Catalog\Domain\Entity\RoadmapSeasonEntity;

interface RoadmapSeasonRepository
{
    public function save(RoadmapSeasonEntity $season): void;

    public function findOneById(int $id): ?RoadmapSeasonEntity;

    public function findOneBySeasonNumber(int $seasonNumber): ?RoadmapSeasonEntity;

    public function findActive(): ?RoadmapSeasonEntity;

    /**
     * @return list<RoadmapSeasonEntity>
     */
    public function findAllOrderedBySeasonNumberDesc(): array;

    public function deactivateAllExcept(?RoadmapSeasonEntity $activeSeason): void;
}
