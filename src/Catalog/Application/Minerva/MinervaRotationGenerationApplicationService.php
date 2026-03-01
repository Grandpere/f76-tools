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

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class MinervaRotationGenerationApplicationService implements MinervaRotationGenerator
{
    private const TIMEZONE = 'America/New_York';
    private const LIST_MIN = 1;
    private const LIST_MAX = 24;

    private const SLOT_DELTAS_DAYS = [
        7,  // regular #1 -> regular #2
        7,  // regular #2 -> regular #3
        10, // regular #3 -> big sale
        4,  // big sale -> regular #1
    ];

    private const SLOT_DURATIONS_DAYS = [
        2, // regular #1
        2, // regular #2
        2, // regular #3
        4, // big sale
    ];

    private const LOCATIONS = [
        'Foundation',
        'Crater',
        'Fort Atlas',
        'The Whitespring Resort',
    ];

    /**
     * Known reference window from public schedule:
     * 2026-03-02 12:00 ET, list 3, location Fort Atlas, regular #3 slot.
     */
    private const ANCHOR_START = '2026-03-02 12:00:00';
    private const ANCHOR_SLOT = 2;
    private const ANCHOR_LIST = 3;
    private const ANCHOR_LOCATION_INDEX = 2;

    /**
     * @return list<array{
     *     location: string,
     *     listCycle: int,
     *     startsAt: DateTimeImmutable,
     *     endsAt: DateTimeImmutable
     * }>
     */
    public function generate(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        if ($to < $from) {
            return [];
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $rangeStart = $from->setTimezone($timezone);
        $rangeEnd = $to->setTimezone($timezone);

        $cursor = [
            'start' => new DateTimeImmutable(self::ANCHOR_START, $timezone),
            'slot' => self::ANCHOR_SLOT,
            'list' => self::ANCHOR_LIST,
            'locationIndex' => self::ANCHOR_LOCATION_INDEX,
        ];

        while ($cursor['start'] > $rangeStart) {
            $cursor = $this->stepBackward($cursor);
        }

        while (true) {
            $window = $this->toWindow($cursor);
            if ($window['startsAt'] > $rangeEnd) {
                break;
            }

            if ($window['endsAt'] >= $rangeStart && $window['startsAt'] <= $rangeEnd) {
                $rows[] = $window;
            }

            $cursor = $this->stepForward($cursor);
        }

        return $rows ?? [];
    }

    /**
     * @param array{start: DateTimeImmutable, slot: int, list: int, locationIndex: int} $state
     *
     * @return array{start: DateTimeImmutable, slot: int, list: int, locationIndex: int}
     */
    private function stepForward(array $state): array
    {
        $deltaDays = self::SLOT_DELTAS_DAYS[$state['slot']];
        $nextSlot = ($state['slot'] + 1) % 4;

        return [
            'start' => $state['start']->add(new DateInterval(sprintf('P%dD', $deltaDays))),
            'slot' => $nextSlot,
            'list' => $this->wrapList($state['list'] + 1),
            'locationIndex' => ($state['locationIndex'] + 1) % count(self::LOCATIONS),
        ];
    }

    /**
     * @param array{start: DateTimeImmutable, slot: int, list: int, locationIndex: int} $state
     *
     * @return array{start: DateTimeImmutable, slot: int, list: int, locationIndex: int}
     */
    private function stepBackward(array $state): array
    {
        $previousSlot = ($state['slot'] + 3) % 4;
        $deltaDays = self::SLOT_DELTAS_DAYS[$previousSlot];
        $locationCount = count(self::LOCATIONS);
        $previousLocationIndex = ($state['locationIndex'] + $locationCount - 1) % $locationCount;

        return [
            'start' => $state['start']->sub(new DateInterval(sprintf('P%dD', $deltaDays))),
            'slot' => $previousSlot,
            'list' => $this->wrapList($state['list'] - 1),
            'locationIndex' => $previousLocationIndex,
        ];
    }

    /**
     * @param array{start: DateTimeImmutable, slot: int, list: int, locationIndex: int} $state
     *
     * @return array{
     *     location: string,
     *     listCycle: int,
     *     startsAt: DateTimeImmutable,
     *     endsAt: DateTimeImmutable
     * }
     */
    private function toWindow(array $state): array
    {
        $durationDays = self::SLOT_DURATIONS_DAYS[$state['slot']];
        $start = $state['start'];

        return [
            'location' => self::LOCATIONS[$state['locationIndex']],
            'listCycle' => $state['list'],
            'startsAt' => $start,
            'endsAt' => $start->add(new DateInterval(sprintf('P%dD', $durationDays))),
        ];
    }

    private function wrapList(int $value): int
    {
        if ($value < self::LIST_MIN) {
            return self::LIST_MAX;
        }
        if ($value > self::LIST_MAX) {
            return self::LIST_MIN;
        }

        return $value;
    }
}
