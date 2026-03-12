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
use App\Catalog\Application\Roadmap\RoadmapTitleComparisonService;
use App\Catalog\Domain\Entity\RoadmapEventEntity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RoadmapTitleComparisonServiceTest extends TestCase
{
    public function testCompareParsedToManualReturnsExpectedMetricsAndMismatches(): void
    {
        $service = new RoadmapTitleComparisonService();

        $ocrEvents = [
            new RoadmapParsedEvent('Mise a jour Borne Zero', new DateTimeImmutable('2024-09-03 16:00:00'), new DateTimeImmutable('2024-09-03 20:00:00')),
            new RoadmapParsedEvent('Événement à vérifier', new DateTimeImmutable('2024-09-05 18:00:00'), new DateTimeImmutable('2024-09-09 18:00:00')),
            new RoadmapParsedEvent('MUTANTS', new DateTimeImmutable('2024-09-12 18:00:00'), new DateTimeImmutable('2024-09-16 18:00:00')),
        ];

        $manualEvents = [
            $this->manualEvent('Mise à jour Borne Zéro', '2024-09-03 16:00:00', '2024-09-03 20:00:00'),
            $this->manualEvent('Doubles mutations et promotion légendaire des marchands', '2024-09-05 18:00:00', '2024-09-09 18:00:00'),
            $this->manualEvent('Événements publics mutants', '2024-09-12 18:00:00', '2024-09-16 18:00:00'),
        ];

        $result = $service->compareParsedToManual($ocrEvents, $manualEvents);

        self::assertSame(3, $result['total_ocr']);
        self::assertSame(3, $result['total_manual']);
        self::assertSame(3, $result['matched_windows']);
        self::assertSame('date', $result['window_mode']);
        self::assertSame(1, $result['exact_matches']);
        self::assertSame(1, $result['placeholder_count']);
        self::assertSame(1, $result['short_title_count']);
        self::assertSame(0, $result['unmatched_ocr_windows']);
        self::assertGreaterThan(0.0, $result['average_similarity']);
        self::assertCount(2, $result['mismatches']);
        self::assertStringContainsString('2024-09-05', $result['mismatches'][0]['window']);
    }

    public function testCompareMatchesByDateWindowEvenWhenHoursDiffer(): void
    {
        $service = new RoadmapTitleComparisonService();

        $ocrEvents = [
            new RoadmapParsedEvent(
                'Mise à jour Borne Zéro',
                new DateTimeImmutable('2024-09-03 16:00:00'),
                new DateTimeImmutable('2024-09-03 20:00:00'),
            ),
        ];

        $manualEvents = [
            $this->manualEvent('Mise à jour Borne Zéro', '2024-09-03 00:00:00', '2024-09-03 23:59:59'),
        ];

        $result = $service->compareParsedToManual($ocrEvents, $manualEvents);

        self::assertSame(1, $result['matched_windows']);
        self::assertSame('date', $result['window_mode']);
        self::assertSame(1, $result['exact_matches']);
        self::assertSame(0, $result['unmatched_ocr_windows']);
    }

    public function testCompareParsedToParsedUsesMonthDayFallbackWhenYearsDiffer(): void
    {
        $service = new RoadmapTitleComparisonService();

        $leftEvents = [
            new RoadmapParsedEvent(
                'Double XP',
                new DateTimeImmutable('2026-11-28 18:00:00'),
                new DateTimeImmutable('2026-12-02 18:00:00'),
            ),
        ];
        $rightEvents = [
            new RoadmapParsedEvent(
                'Double XP et Mutations',
                new DateTimeImmutable('2024-11-28 18:00:00'),
                new DateTimeImmutable('2024-12-02 18:00:00'),
            ),
        ];

        $result = $service->compareParsedToParsed($leftEvents, $rightEvents);

        self::assertSame(1, $result['matched_windows']);
        self::assertSame('month_day', $result['window_mode']);
        self::assertSame(0, $result['exact_matches']);
        self::assertGreaterThan(0.0, $result['average_similarity']);
        self::assertCount(1, $result['mismatches']);
    }

    private function manualEvent(string $title, string $startsAt, string $endsAt): RoadmapEventEntity
    {
        return (new RoadmapEventEntity())
            ->setLocale('fr')
            ->setTitle($title)
            ->setStartsAt(new DateTimeImmutable($startsAt))
            ->setEndsAt(new DateTimeImmutable($endsAt));
    }
}
