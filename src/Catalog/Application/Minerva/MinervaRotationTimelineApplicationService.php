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

use App\Domain\Minerva\MinervaRotationStatusEnum;
use DateTimeImmutable;
use DateTimeZone;

final class MinervaRotationTimelineApplicationService
{
    private const TIMELINE_TIMEZONE = 'UTC';

    public function __construct(
        private readonly MinervaRotationReader $rotationReader,
    ) {
    }

    /**
     * @return array{
     *     timezone: string,
     *     generatedAt: string,
     *     rows: list<array{
     *         id: int,
     *         location: string,
     *         listCycle: int,
     *         startsAt: string,
     *         endsAt: string,
     *         status: string
     *     }>
     * }
     */
    public function buildTimeline(?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone(self::TIMELINE_TIMEZONE);
        $reference = $now instanceof DateTimeImmutable
            ? $now->setTimezone($timezone)
            : new DateTimeImmutable('now', $timezone);

        $rows = [];
        foreach ($this->rotationReader->findAllOrdered() as $rotation) {
            $startsAt = $rotation->getStartsAt()->setTimezone($timezone);
            $endsAt = $rotation->getEndsAt()->setTimezone($timezone);
            $status = $this->resolveStatus($reference, $startsAt, $endsAt);
            $id = $rotation->getId();
            if (null === $id) {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'location' => $rotation->getLocation(),
                'listCycle' => $rotation->getListCycle(),
                'startsAt' => $startsAt->format(DATE_ATOM),
                'endsAt' => $endsAt->format(DATE_ATOM),
                'status' => $status->value,
            ];
        }

        return [
            'timezone' => self::TIMELINE_TIMEZONE,
            'generatedAt' => $reference->format(DATE_ATOM),
            'rows' => $rows,
        ];
    }

    private function resolveStatus(
        DateTimeImmutable $reference,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): MinervaRotationStatusEnum {
        if ($reference < $startsAt) {
            return MinervaRotationStatusEnum::UPCOMING;
        }
        if ($reference > $endsAt) {
            return MinervaRotationStatusEnum::ENDED;
        }

        return MinervaRotationStatusEnum::ACTIVE;
    }
}
