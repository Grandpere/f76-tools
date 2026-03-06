<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Roadmap;

use App\Catalog\Application\Roadmap\RoadmapRawTextEventParser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RoadmapRawTextEventParserTest extends TestCase
{
    public function testParseFrenchDateRangesWithFollowingTitle(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
3 MARS - 10 MARS
LA FETE DU YETI

10 MARS - 24 MARS
ENVAHISSEURS D'AU-DELA
TXT;

        $events = $parser->parse($text, 'fr', new DateTimeImmutable('2026-03-02 10:00:00'));

        self::assertCount(2, $events);
        self::assertSame('LA FETE DU YETI', $events[0]->title);
        self::assertSame('2026-03-03 00:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-10 23:59:59', $events[0]->endsAt->format('Y-m-d H:i:s'));
        self::assertSame('ENVAHISSEURS D\'AU-DELA', $events[1]->title);
    }

    public function testParseEnglishDateRangesWithUppercaseMonth(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
7 APRIL - 14 APRIL
DOUBLE XP
TXT;

        $events = $parser->parse($text, 'en', new DateTimeImmutable('2026-04-01 00:00:00'));

        self::assertCount(1, $events);
        self::assertSame('DOUBLE XP', $events[0]->title);
        self::assertSame('2026-04-07 00:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-14 23:59:59', $events[0]->endsAt->format('Y-m-d H:i:s'));
    }

    public function testParseGermanDateRangesWithUmlautNormalization(): void
    {
        $parser = new RoadmapRawTextEventParser();
        $text = <<<TXT
5 MÄRZ - 12 MÄRZ
DOPPELTE MUTATIONEN
TXT;

        $events = $parser->parse($text, 'de', new DateTimeImmutable('2026-03-01 00:00:00'));

        self::assertCount(1, $events);
        self::assertSame('DOPPELTE MUTATIONEN', $events[0]->title);
        self::assertSame('2026-03-05 00:00:00', $events[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-12 23:59:59', $events[0]->endsAt->format('Y-m-d H:i:s'));
    }
}

