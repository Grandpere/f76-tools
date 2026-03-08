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
use App\Catalog\Application\Roadmap\RoadmapRawTextEventParser;
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;
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
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            $canonicalRepo,
        );

        $result = $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], false);

        self::assertSame(2, $result->totalEvents);
        self::assertSame(1, $result->highConfidenceEvents);
        self::assertSame(1, $result->mediumConfidenceEvents);
        self::assertSame(0, $result->lowConfidenceEvents);
        self::assertTrue($canonicalRepo->cleared);
        self::assertCount(2, $canonicalRepo->savedEvents);
    }

    public function testMergeDryRunDoesNotPersist(): void
    {
        $fr = $this->snapshot(1, 'fr', "3 MARS - 10 MARS\nFETE DU YETI");
        $en = $this->snapshot(2, 'en', "MAR 3 - MAR 10\nBIGFOOT'S BASH");
        $de = $this->snapshot(3, 'de', "3. BIS 10. MARZ\nBIGFOOTS PARTY");

        $snapshotRepo = new InMemorySnapshotRepoForMerge([$fr, $en, $de]);
        $canonicalRepo = new InMemoryCanonicalRepoForMerge();
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            $canonicalRepo,
        );

        $result = $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], true);

        self::assertSame(1, $result->totalEvents);
        self::assertFalse($canonicalRepo->cleared);
        self::assertCount(0, $canonicalRepo->savedEvents);
    }

    public function testMergeAddsWarningForPotentialOcrDayMismatchAcrossLocales(): void
    {
        $fr = $this->snapshot(1, 'fr', "3 MAI - 1ER JUIN\nFIEVRE DE L OR");
        $en = $this->snapshot(2, 'en', "MAY 28 - JUNE 1\nGOLD RUSH");
        $de = $this->snapshot(3, 'de', "28. MAI BIS 1. JUNI\nGOLDRAUSCH");

        $snapshotRepo = new InMemorySnapshotRepoForMerge([$fr, $en, $de]);
        $canonicalRepo = new InMemoryCanonicalRepoForMerge();
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            $canonicalRepo,
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
        $service = new MergeRoadmapLocalesApplicationService(
            $snapshotRepo,
            new RoadmapRawTextEventParser(),
            $canonicalRepo,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be approved before merge');

        $service->merge(['fr' => 1, 'en' => 2, 'de' => 3], true);
    }

    private function snapshot(int $id, string $locale, string $rawText): RoadmapSnapshotEntity
    {
        $snapshot = new RoadmapSnapshotEntity()
            ->setLocale($locale)
            ->setSourceImagePath('/tmp/mock-'.$locale.'.jpg')
            ->setSourceImageHash(str_repeat('a', 64))
            ->setOcrProvider('ocr.space')
            ->setOcrConfidence(0.95)
            ->setRawText($rawText)
            ->setStatus(RoadmapSnapshotStatusEnum::APPROVED);

        $reflection = new ReflectionClass($snapshot);
        $property = $reflection->getProperty('id');
        $property->setValue($snapshot, $id);

        return $snapshot;
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

    public function findRecent(int $limit = 20): array
    {
        if ($limit <= 0) {
            return [];
        }

        return array_slice(array_values($this->snapshots), 0, $limit);
    }
}

final class InMemoryCanonicalRepoForMerge implements RoadmapCanonicalEventWriteRepository
{
    public bool $cleared = false;

    /** @var list<RoadmapCanonicalEventEntity> */
    public array $savedEvents = [];

    public function clearAll(): void
    {
        $this->cleared = true;
        $this->savedEvents = [];
    }

    public function saveAll(array $events): void
    {
        $this->savedEvents = $events;
    }
}
