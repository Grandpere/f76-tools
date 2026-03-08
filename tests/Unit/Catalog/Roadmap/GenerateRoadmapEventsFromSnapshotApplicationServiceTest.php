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

use App\Catalog\Application\Roadmap\GenerateRoadmapEventsFromSnapshotApplicationService;
use App\Catalog\Application\Roadmap\RoadmapRawTextEventParser;
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class GenerateRoadmapEventsFromSnapshotApplicationServiceTest extends TestCase
{
    public function testGeneratePersistsEventsWhenNotDryRun(): void
    {
        $snapshot = new RoadmapSnapshotEntity()
            ->setLocale('fr')
            ->setSourceImagePath('/tmp/mock.jpg')
            ->setSourceImageHash(str_repeat('a', 64))
            ->setOcrProvider('ocr.space')
            ->setOcrConfidence(0.91)
            ->setRawText("3 MARS - 10 MARS\nLA FETE DU YETI\n");
        $this->forceId($snapshot, 1);

        $repo = new InMemorySnapshotRepoForEventGeneration([$snapshot]);
        $service = new GenerateRoadmapEventsFromSnapshotApplicationService($repo, new RoadmapRawTextEventParser());

        $events = $service->generate(1, false);

        self::assertCount(1, $events);
        $saved = $repo->findOneById(1);
        self::assertNotNull($saved);
        self::assertCount(1, $saved->getEvents());
    }

    public function testGenerateThrowsOnMissingSnapshot(): void
    {
        $service = new GenerateRoadmapEventsFromSnapshotApplicationService(
            new InMemorySnapshotRepoForEventGeneration([]),
            new RoadmapRawTextEventParser(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found');
        $service->generate(123, true);
    }

    private function forceId(RoadmapSnapshotEntity $snapshot, int $id): void
    {
        $reflection = new ReflectionClass($snapshot);
        $property = $reflection->getProperty('id');
        $property->setValue($snapshot, $id);
    }
}

final class InMemorySnapshotRepoForEventGeneration implements RoadmapSnapshotWriteRepository
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
            throw new RuntimeException('Snapshot id is required in in-memory repository.');
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
        $snapshot = $this->snapshots[$id] ?? null;

        return $snapshot instanceof RoadmapSnapshotEntity ? $snapshot : null;
    }

    public function findRecent(int $limit = 20): array
    {
        if ($limit <= 0) {
            return [];
        }

        return array_slice(array_values($this->snapshots), 0, $limit);
    }
}
