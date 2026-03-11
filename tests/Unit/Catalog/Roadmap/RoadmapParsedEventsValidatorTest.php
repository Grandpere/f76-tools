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
}

