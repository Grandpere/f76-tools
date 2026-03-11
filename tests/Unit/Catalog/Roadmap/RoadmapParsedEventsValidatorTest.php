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

namespace App\Tests\Unit\Catalog\Roadmap;

use App\Catalog\Application\Roadmap\RoadmapParsedEvent;
use App\Catalog\Application\Roadmap\RoadmapParsedEventsValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RoadmapParsedEventsValidatorTest extends TestCase
{
    public function testSeasonLikeTextFailsWhenTooFewEventsAreDetected(): void
    {
        $validator = new RoadmapParsedEventsValidator();
        $events = [
            new RoadmapParsedEvent(
                'Bigfoot Bash',
                new DateTimeImmutable('2026-03-03 18:00:00'),
                new DateTimeImmutable('2026-03-10 18:00:00'),
            ),
        ];

        $result = $validator->validate($events, 'fr', "FALLOUT 76 SEASON 24\nCOMMUNITY CALENDAR");

        self::assertTrue($result->hasErrors());
        self::assertStringContainsString('Too few events', $result->errors[0]);
    }

    public function testChronologyErrorsAreDetected(): void
    {
        $validator = new RoadmapParsedEventsValidator();
        $events = [
            new RoadmapParsedEvent(
                'Event B',
                new DateTimeImmutable('2026-03-10 18:00:00'),
                new DateTimeImmutable('2026-03-12 18:00:00'),
            ),
            new RoadmapParsedEvent(
                'Event A',
                new DateTimeImmutable('2026-03-03 18:00:00'),
                new DateTimeImmutable('2026-03-10 18:00:00'),
            ),
        ];

        $result = $validator->validate($events, 'en', '');

        self::assertTrue($result->hasErrors());
        self::assertStringContainsString('not chronological', implode(' ', $result->errors));
    }

    public function testPartialSeasonTextAllowsLowerMinimumEventCount(): void
    {
        $validator = new RoadmapParsedEventsValidator();
        $events = [];
        for ($i = 0; $i < 7; ++$i) {
            $day = 1 + ($i * 2);
            $events[] = new RoadmapParsedEvent(
                'Event '.($i + 1),
                new DateTimeImmutable(sprintf('2026-08-%02d 18:00:00', $day)),
                new DateTimeImmutable(sprintf('2026-08-%02d 18:00:00', $day + 1)),
            );
        }

        $result = $validator->validate(
            $events,
            'de',
            "FALLOUT 76 SEASON 21\nCOMMUNITY CALENDAR\nAUGUST AND SEPTEMBER COMING SOON...",
        );

        self::assertFalse($result->hasErrors());
    }

    public function testPartialSeasonWindowAllowsLowerMinimumWithoutKeyword(): void
    {
        $validator = new RoadmapParsedEventsValidator();
        $events = [
            new RoadmapParsedEvent('A', new DateTimeImmutable('2025-07-29 18:00:00'), new DateTimeImmutable('2025-08-12 18:00:00')),
            new RoadmapParsedEvent('B', new DateTimeImmutable('2025-07-31 18:00:00'), new DateTimeImmutable('2025-08-04 18:00:00')),
            new RoadmapParsedEvent('C', new DateTimeImmutable('2025-08-07 18:00:00'), new DateTimeImmutable('2025-08-11 18:00:00')),
            new RoadmapParsedEvent('D', new DateTimeImmutable('2025-08-12 18:00:00'), new DateTimeImmutable('2025-08-26 18:00:00')),
            new RoadmapParsedEvent('E', new DateTimeImmutable('2025-08-14 18:00:00'), new DateTimeImmutable('2025-08-18 18:00:00')),
            new RoadmapParsedEvent('F', new DateTimeImmutable('2025-08-19 18:00:00'), new DateTimeImmutable('2025-08-26 18:00:00')),
            new RoadmapParsedEvent('G', new DateTimeImmutable('2025-08-28 18:00:00'), new DateTimeImmutable('2025-09-01 18:00:00')),
        ];

        $result = $validator->validate(
            $events,
            'de',
            "FALLOUT 76 SEASON 21\nCOMMUNITY CALENDAR",
        );

        self::assertFalse($result->hasErrors());
    }
}
