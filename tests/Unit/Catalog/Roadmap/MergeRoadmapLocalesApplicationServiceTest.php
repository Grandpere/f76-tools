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

use App\Catalog\Application\Roadmap\MergeRoadmapLocalesApplicationService;
use App\Catalog\Application\Roadmap\RoadmapCanonicalEventWriteRepository;
use App\Catalog\Application\Roadmap\RoadmapParsedEventsValidator;
use App\Catalog\Application\Roadmap\RoadmapRawTextEventParser;
use App\Catalog\Application\Roadmap\RoadmapSeasonRepository;
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;
use App\Catalog\Domain\Entity\RoadmapSeasonEntity;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use App\Catalog\Domain\Roadmap\RoadmapSnapshotStatusEnum;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class MergeRoadmapLocalesApplicationServiceTest extends TestCase
{
    public function testMergeBuildsCanonicalEventsWithConfidenceBuckets(): void
    {
        $fr = $this->snapshot(1, 'fr', "3 MARS - 10 MARS\nFETE DU YETI\n10 MARS - 24 MARS\nINVADERS");
        $en = $this->snapshot(2, 'en', "MAR 3 - MAR 10\nBIGFOOT'S BASH");
        $de = $this->snapshot(3, 'de', "3. BIS 10. MARZ\nBIGFOOTS PARTY\n10. BIS 24. MARZ\nINVASOREN");

        $snapshotRepo = new InMemorySnapshotRepoForMerge([$fr, $en, $de]);
        $canonicalRepo = new InMemoryCanonicalRepoForMerge();
        $seasonRepo = new InMemorySeasonRepoForMerge([$this->season(24, true)]);
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            new RoadmapParsedEventsValidator(),
            $canonicalRepo,
            $seasonRepo,
        );

        $result = $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], false);

        self::assertSame(2, $result->totalEvents);
        self::assertSame(1, $result->highConfidenceEvents);
        self::assertSame(1, $result->mediumConfidenceEvents);
        self::assertSame(0, $result->lowConfidenceEvents);
        self::assertTrue($canonicalRepo->clearedBySeason);
        self::assertCount(2, $canonicalRepo->savedEvents);
        self::assertSame(24, $seasonRepo->findActive()?->getSeasonNumber());
    }

    public function testMergeDryRunDoesNotPersist(): void
    {
        $fr = $this->snapshot(1, 'fr', "3 MARS - 10 MARS\nFETE DU YETI");
        $en = $this->snapshot(2, 'en', "MAR 3 - MAR 10\nBIGFOOT'S BASH");
        $de = $this->snapshot(3, 'de', "3. BIS 10. MARZ\nBIGFOOTS PARTY");

        $snapshotRepo = new InMemorySnapshotRepoForMerge([$fr, $en, $de]);
        $canonicalRepo = new InMemoryCanonicalRepoForMerge();
        $seasonRepo = new InMemorySeasonRepoForMerge([$this->season(24, false)]);
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            new RoadmapParsedEventsValidator(),
            $canonicalRepo,
            $seasonRepo,
        );

        $result = $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], true);

        self::assertSame(1, $result->totalEvents);
        self::assertFalse($canonicalRepo->clearedBySeason);
        self::assertCount(0, $canonicalRepo->savedEvents);
    }

    public function testMergeAddsWarningForPotentialOcrDayMismatchAcrossLocales(): void
    {
        $fr = $this->snapshot(1, 'fr', "3 MAI - 1ER JUIN\nFIEVRE DE L OR");
        $en = $this->snapshot(2, 'en', "MAY 28 - JUNE 1\nGOLD RUSH");
        $de = $this->snapshot(3, 'de', "28. MAI BIS 1. JUNI\nGOLDRAUSCH");

        $snapshotRepo = new InMemorySnapshotRepoForMerge([$fr, $en, $de]);
        $canonicalRepo = new InMemoryCanonicalRepoForMerge();
        $seasonRepo = new InMemorySeasonRepoForMerge([$this->season(24, false)]);
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            new RoadmapParsedEventsValidator(),
            $canonicalRepo,
            $seasonRepo,
        );

        $result = $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], true);

        self::assertSame(2, $result->totalEvents);
        self::assertTrue($this->containsWarningFragment($result->warnings, 'Potential OCR day mismatch'));
        self::assertTrue($this->containsWarningFragment($result->warnings, '2026-06-01'));
    }

    public function testMergeThrowsWhenSnapshotIsNotApproved(): void
    {
        $fr = $this->snapshot(1, 'fr', "3 MARS - 10 MARS\nFETE DU YETI");
        $fr->setStatus(RoadmapSnapshotStatusEnum::DRAFT);
        $en = $this->snapshot(2, 'en', "MAR 3 - MAR 10\nBIGFOOT'S BASH");
        $de = $this->snapshot(3, 'de', "3. BIS 10. MARZ\nBIGFOOTS PARTY");

        $snapshotRepo = new InMemorySnapshotRepoForMerge([$fr, $en, $de]);
        $canonicalRepo = new InMemoryCanonicalRepoForMerge();
        $seasonRepo = new InMemorySeasonRepoForMerge([$this->season(24, false)]);
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            new RoadmapParsedEventsValidator(),
            $canonicalRepo,
            $seasonRepo,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be approved before merge');

        $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], true);
    }

    public function testMergeThrowsWhenSnapshotsBelongToDifferentSeasons(): void
    {
        $fr = $this->snapshot(1, 'fr', "3 MARS - 10 MARS\nFETE DU YETI");
        $en = $this->snapshot(2, 'en', "MAR 3 - MAR 10\nBIGFOOT'S BASH");
        $de = $this->snapshot(3, 'de', "3. BIS 10. MARZ\nBIGFOOTS PARTY");
        $de->setSeason($this->season(25, false));

        $snapshotRepo = new InMemorySnapshotRepoForMerge([$fr, $en, $de]);
        $canonicalRepo = new InMemoryCanonicalRepoForMerge();
        $seasonRepo = new InMemorySeasonRepoForMerge([$this->season(24, false), $this->season(25, false)]);
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            new RoadmapParsedEventsValidator(),
            $canonicalRepo,
            $seasonRepo,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('same season');

        $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], true);
    }

    public function testMergeThrowsWhenQualityChecksFailForSeasonLikeSnapshot(): void
    {
        $fr = $this->snapshot(1, 'fr', "FALLOUT 76 SEASON 01\nCOMMUNITY CALENDAR\n3 MARS - 10 MARS\nFETE DU YETI");
        $en = $this->snapshot(2, 'en', "FALLOUT 76 SEASON 01\nCOMMUNITY CALENDAR\nMAR 3 - MAR 10\nBIGFOOT'S BASH");
        $de = $this->snapshot(3, 'de', "FALLOUT 76 SEASON 01\nCOMMUNITY CALENDAR\n3. BIS 10. MARZ\nBIGFOOTS PARTY");

        $snapshotRepo = new InMemorySnapshotRepoForMerge([$fr, $en, $de]);
        $canonicalRepo = new InMemoryCanonicalRepoForMerge();
        $seasonRepo = new InMemorySeasonRepoForMerge([$this->season(24, false)]);
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            new RoadmapParsedEventsValidator(),
            $canonicalRepo,
            $seasonRepo,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed quality checks');

        $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], true);
    }

    public function testMergeUsesPersistedSnapshotEventsWhenAvailable(): void
    {
        $fr = $this->snapshot(1, 'fr', "3 MARS - 10 MARS\nRAW TITLE");
        $fr->clearEvents();
        $fr->addEvent(
            (new \App\Catalog\Domain\Entity\RoadmapEventEntity())
                ->setLocale('fr')
                ->setTitle('MANUAL TITLE FR')
                ->setStartsAt(new \DateTimeImmutable('2026-03-03 18:00:00'))
                ->setEndsAt(new \DateTimeImmutable('2026-03-10 18:00:00'))
                ->setSortOrder(1),
        );
        $en = $this->snapshot(2, 'en', "MAR 3 - MAR 10\nMANUAL TITLE EN");
        $de = $this->snapshot(3, 'de', "3. BIS 10. MARZ\nMANUAL TITLE DE");

        $snapshotRepo = new InMemorySnapshotRepoForMerge([$fr, $en, $de]);
        $canonicalRepo = new InMemoryCanonicalRepoForMerge();
        $seasonRepo = new InMemorySeasonRepoForMerge([$this->season(24, true)]);
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            new RoadmapParsedEventsValidator(),
            $canonicalRepo,
            $seasonRepo,
        );

        $result = $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], false);

        self::assertSame(1, $result->totalEvents);
        self::assertCount(1, $canonicalRepo->savedEvents);
        $saved = $canonicalRepo->savedEvents[0];
        $frTranslation = null;
        foreach ($saved->getTranslations() as $translation) {
            if ('fr' === $translation->getLocale()) {
                $frTranslation = $translation->getTitle();
            }
        }
        self::assertSame('MANUAL TITLE FR', $frTranslation);
    }

    private function snapshot(int $id, string $locale, string $rawText): RoadmapSnapshotEntity
    {
        $season = $this->season(24, false);
        $snapshot = new RoadmapSnapshotEntity()
            ->setLocale($locale)
            ->setSourceImagePath('/tmp/mock-'.$locale.'.jpg')
            ->setSourceImageHash(str_repeat('a', 64))
            ->setOcrProvider('ocr.space')
            ->setOcrConfidence(0.95)
            ->setRawText($rawText)
            ->setSeason($season)
            ->setStatus(RoadmapSnapshotStatusEnum::APPROVED);

        $reflection = new ReflectionClass($snapshot);
        $property = $reflection->getProperty('id');
        $property->setValue($snapshot, $id);

        return $snapshot;
    }

    private function season(int $number, bool $active): RoadmapSeasonEntity
    {
        $season = new RoadmapSeasonEntity()
            ->setSeasonNumber($number)
            ->setTitle(sprintf('Season %d', $number))
            ->setIsActive($active);

        $reflection = new ReflectionClass($season);
        $property = $reflection->getProperty('id');
        $property->setValue($season, $number);

        return $season;
    }

    /**
     * @param list<string> $warnings
     */
    private function containsWarningFragment(array $warnings, string $expectedFragment): bool
    {
        foreach ($warnings as $warning) {
            if (str_contains($warning, $expectedFragment)) {
                return true;
            }
        }

        return false;
    }
}

final class InMemorySnapshotRepoForMerge implements RoadmapSnapshotWriteRepository
{
    /** @var array<int, RoadmapSnapshotEntity> */
    private array $snapshots = [];

    /**
     * @param list<RoadmapSnapshotEntity> $snapshots
     */
    public function __construct(array $snapshots)
    {
        foreach ($snapshots as $snapshot) {
            $id = $snapshot->getId();
            if (is_int($id)) {
                $this->snapshots[$id] = $snapshot;
            }
        }
    }

    public function save(RoadmapSnapshotEntity $snapshot): void
    {
        $id = $snapshot->getId();
        if (!is_int($id)) {
            throw new RuntimeException('Snapshot id is required.');
        }
        $this->snapshots[$id] = $snapshot;
    }

    public function delete(RoadmapSnapshotEntity $snapshot): void
    {
        $id = $snapshot->getId();
        if (!is_int($id)) {
            return;
        }

        unset($this->snapshots[$id]);
    }

    public function findOneById(int $id): ?RoadmapSnapshotEntity
    {
        return $this->snapshots[$id] ?? null;
    }

    public function findOneWithEventsById(int $id): ?RoadmapSnapshotEntity
    {
        return $this->findOneById($id);
    }

    public function findRecent(int $limit = 20, ?RoadmapSeasonEntity $season = null): array
    {
        if ($limit <= 0) {
            return [];
        }

        $items = array_values($this->snapshots);
        if ($season instanceof RoadmapSeasonEntity) {
            $items = array_values(array_filter($items, static fn (RoadmapSnapshotEntity $item): bool => $item->getSeason()?->getId() === $season->getId()));
        }

        return array_slice($items, 0, $limit);
    }
}

final class InMemoryCanonicalRepoForMerge implements RoadmapCanonicalEventWriteRepository
{
    public bool $cleared = false;
    public bool $clearedBySeason = false;

    /** @var list<RoadmapCanonicalEventEntity> */
    public array $savedEvents = [];

    public function clearAll(): void
    {
        $this->cleared = true;
        $this->savedEvents = [];
    }

    public function clearBySeason(RoadmapSeasonEntity $season): void
    {
        $this->clearedBySeason = true;
        $this->savedEvents = [];
    }

    public function saveAll(array $events): void
    {
        $this->savedEvents = $events;
    }
}

final class InMemorySeasonRepoForMerge implements RoadmapSeasonRepository
{
    /** @var array<int, RoadmapSeasonEntity> */
    private array $items = [];

    /**
     * @param list<RoadmapSeasonEntity> $seasons
     */
    public function __construct(array $seasons)
    {
        foreach ($seasons as $season) {
            $id = $season->getId();
            if (is_int($id)) {
                $this->items[$id] = $season;
            }
        }
    }

    public function save(RoadmapSeasonEntity $season): void
    {
        $id = $season->getId();
        if (is_int($id)) {
            $this->items[$id] = $season;
        }
    }

    public function findOneById(int $id): ?RoadmapSeasonEntity
    {
        return $this->items[$id] ?? null;
    }

    public function findOneBySeasonNumber(int $seasonNumber): ?RoadmapSeasonEntity
    {
        foreach ($this->items as $item) {
            if ($item->getSeasonNumber() === $seasonNumber) {
                return $item;
            }
        }

        return null;
    }

    public function findActive(): ?RoadmapSeasonEntity
    {
        foreach ($this->items as $item) {
            if ($item->isActive()) {
                return $item;
            }
        }

        return null;
    }

    public function findAllOrderedBySeasonNumberDesc(): array
    {
        $items = array_values($this->items);
        usort($items, static fn (RoadmapSeasonEntity $a, RoadmapSeasonEntity $b): int => $b->getSeasonNumber() <=> $a->getSeasonNumber());

        return $items;
    }

    public function deactivateAllExcept(?RoadmapSeasonEntity $activeSeason): void
    {
        $activeId = $activeSeason?->getId();
        foreach ($this->items as $item) {
            $item->setIsActive(is_int($activeId) && $item->getId() === $activeId);
        }
    }
}
