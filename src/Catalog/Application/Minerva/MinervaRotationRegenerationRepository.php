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

namespace App\Catalog\Application\Minerva;

use App\Catalog\Domain\Entity\MinervaRotationEntity;
use App\Catalog\Domain\Minerva\MinervaRotationSourceEnum;
use DateTimeImmutable;

interface MinervaRotationRegenerationRepository
{
    public function deleteOverlappingGeneratedRange(DateTimeImmutable $from, DateTimeImmutable $to): int;

    /**
     * @return list<MinervaRotationEntity>
     */
    public function findOverlappingRange(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return list<MinervaRotationEntity>
     */
    public function findManualOverlappingRange(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return list<MinervaRotationEntity>
     */
    public function findManualOrdered(): array;

    public function findManualById(int $id): ?MinervaRotationEntity;

    public function findLatestCreatedAtBySource(MinervaRotationSourceEnum $source): ?DateTimeImmutable;
}
