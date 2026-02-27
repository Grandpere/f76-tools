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

use App\Catalog\Domain\Minerva\MinervaRotationStatusEnum;
use DateTimeImmutable;
use DateTimeZone;

final class MinervaRotationTimelineApplicationService
{
    private const TIMELINE_TIMEZONE = 'America/New_York';

    public function __construct(
        private readonly MinervaRotationReader $rotationReader,
    ) {
    }

    /**
     * @return array{
     *     timezone: string,
     *     generatedAt: string,
     *     current: array{
     *         id: int,
     *         location: string,
     *         listCycle: int,
     *         startsAt: string,
     *         endsAt: string,
     *         source: string,
     *         status: string
     *     }|null,
     *     upcoming: list<array{
     *         id: int,
     *         location: string,
     *         listCycle: int,
     *         startsAt: string,
     *         endsAt: string,
     *         source: string,
     *         status: string
     *     }>,
     *     rows: list<array{
     *         id: int,
     *         location: string,
     *         listCycle: int,
     *         startsAt: string,
     *         endsAt: string,
     *         source: string,
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

        /** @var list<array{id:int,location:string,listCycle:int,startsAt:string,endsAt:string,source:string,status:string}> $typedRows */
        $typedRows = [];
        $rows = [];
        foreach ($this->rotationReader->findAllOrdered() as $rotation) {
            $startsAt = $this->normalizeStoredWallTime($rotation->getStartsAt(), $timezone);
            $endsAt = $this->normalizeStoredWallTime($rotation->getEndsAt(), $timezone);
            $status = $this->resolveStatus($reference, $startsAt, $endsAt);
            $id = $rotation->getId();
            if (null === $id) {
                continue;
            }

            $row = [
                'id' => $id,
                'location' => $rotation->getLocation(),
                'listCycle' => $rotation->getListCycle(),
                'startsAt' => $startsAt->format(DATE_ATOM),
                'endsAt' => $endsAt->format(DATE_ATOM),
                'source' => $rotation->getSource()->value,
                'status' => $status->value,
            ];
            $rows[] = $row;
            $typedRows[] = $row;
        }
        $current = $this->resolveCurrentWindow($typedRows);
        $upcoming = $this->resolveUpcomingWindows($typedRows, 3);

        return [
            'timezone' => self::TIMELINE_TIMEZONE,
            'generatedAt' => $reference->format(DATE_ATOM),
            'current' => $current,
            'upcoming' => $upcoming,
            'rows' => $rows,
        ];
    }

    /**
     * @param list<array{id:int,location:string,listCycle:int,startsAt:string,endsAt:string,source:string,status:string}> $rows
     *
     * @return array{id:int,location:string,listCycle:int,startsAt:string,endsAt:string,source:string,status:string}|null
     */
    private function resolveCurrentWindow(array $rows): ?array
    {
        foreach ($rows as $row) {
            if (MinervaRotationStatusEnum::ACTIVE->value === $row['status']) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param list<array{id:int,location:string,listCycle:int,startsAt:string,endsAt:string,source:string,status:string}> $rows
     *
     * @return list<array{id:int,location:string,listCycle:int,startsAt:string,endsAt:string,source:string,status:string}>
     */
    private function resolveUpcomingWindows(array $rows, int $limit): array
    {
        $upcoming = [];
        foreach ($rows as $row) {
            if (MinervaRotationStatusEnum::UPCOMING->value !== $row['status']) {
                continue;
            }
            $upcoming[] = $row;
            if (count($upcoming) >= $limit) {
                break;
            }
        }

        return $upcoming;
    }

    private function normalizeStoredWallTime(DateTimeImmutable $value, DateTimeZone $timezone): DateTimeImmutable
    {
        $reparsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value->format('Y-m-d H:i:s'), $timezone);
        if ($reparsed instanceof DateTimeImmutable) {
            return $reparsed;
        }

        return $value->setTimezone($timezone);
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
