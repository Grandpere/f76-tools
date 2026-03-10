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

use App\Catalog\Application\Roadmap\ApproveRoadmapSnapshotApplicationService;
use App\Catalog\Application\Roadmap\CreateRoadmapSnapshotApplicationService;
use App\Catalog\Application\Roadmap\CreateRoadmapSnapshotInput;
use App\Catalog\Application\Roadmap\RoadmapSeasonExtractor;
use App\Catalog\Application\Roadmap\RoadmapSeasonRepository;
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapSeasonEntity;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use App\Catalog\Domain\Roadmap\RoadmapSnapshotStatusEnum;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class CreateAndApproveRoadmapSnapshotApplicationServiceTest extends TestCase
{
    public function testCreatePersistsDraftSnapshot(): void
    {
        $repository = new InMemoryRoadmapSnapshotWriteRepository();
        $seasonRepository = new InMemoryRoadmapSeasonRepository();
        $service = new CreateRoadmapSnapshotApplicationService($repository, $seasonRepository, new RoadmapSeasonExtractor());

        $imagePath = tempnam(sys_get_temp_dir(), 'roadmap-snapshot-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'test-image');

        $snapshot = $service->create(new CreateRoadmapSnapshotInput(
            'fr',
            $imagePath,
            'ocr.space',
            0.91,
            "SAISON 24\nHello world",
        ));

        self::assertSame(RoadmapSnapshotStatusEnum::DRAFT, $snapshot->getStatus());
        self::assertSame('fr', $snapshot->getLocale());
        self::assertNotNull($snapshot->getId());
        self::assertSame(24, $snapshot->getSeason()?->getSeasonNumber());

        @unlink($imagePath);
    }

    public function testApproveSwitchesStatusToApproved(): void
    {
        $repository = new InMemoryRoadmapSnapshotWriteRepository();
        $seasonRepository = new InMemoryRoadmapSeasonRepository();
        $create = new CreateRoadmapSnapshotApplicationService($repository, $seasonRepository, new RoadmapSeasonExtractor());
        $approve = new ApproveRoadmapSnapshotApplicationService($repository);

        $imagePath = tempnam(sys_get_temp_dir(), 'roadmap-snapshot-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'test-image');

        $snapshot = $create->create(new CreateRoadmapSnapshotInput(
            'en',
            $imagePath,
            'ocr.space',
            0.92,
            "SEASON 24\nHello world",
        ));
        $snapshotId = $snapshot->getId();
        self::assertNotNull($snapshotId);

        $approve->approve($snapshotId);

        $approved = $repository->findOneById($snapshotId);
        self::assertNotNull($approved);
        self::assertSame(RoadmapSnapshotStatusEnum::APPROVED, $approved->getStatus());
        self::assertNotNull($approved->getApprovedAt());

        @unlink($imagePath);
    }

    public function testApproveThrowsWhenSnapshotNotFound(): void
    {
        $service = new ApproveRoadmapSnapshotApplicationService(new InMemoryRoadmapSnapshotWriteRepository());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found');
        $service->approve(999);
    }
}

final class InMemoryRoadmapSnapshotWriteRepository implements RoadmapSnapshotWriteRepository
{
    /** @var array<int, RoadmapSnapshotEntity> */
    private array $items = [];

    private int $nextId = 1;

    public function save(RoadmapSnapshotEntity $snapshot): void
    {
        $id = $snapshot->getId();
        if (null === $id) {
            $this->forceId($snapshot, $this->nextId++);
            $id = $snapshot->getId();
        }

        if (!is_int($id)) {
            throw new RuntimeException('Snapshot id must be initialized in in-memory repository.');
        }

        $this->items[$id] = $snapshot;
    }

    public function delete(RoadmapSnapshotEntity $snapshot): void
    {
        $id = $snapshot->getId();
        if (!is_int($id)) {
            return;
        }

        unset($this->items[$id]);
    }

    public function findOneById(int $id): ?RoadmapSnapshotEntity
    {
        return $this->items[$id] ?? null;
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

        $items = array_values($this->items);
        if ($season instanceof RoadmapSeasonEntity) {
            $items = array_values(array_filter($items, static fn (RoadmapSnapshotEntity $item): bool => $item->getSeason()?->getId() === $season->getId()));
        }

        return array_slice($items, 0, $limit);
    }

    private function forceId(RoadmapSnapshotEntity $snapshot, int $id): void
    {
        $reflection = new ReflectionClass($snapshot);
        $property = $reflection->getProperty('id');
        $property->setValue($snapshot, $id);
    }
}

final class InMemoryRoadmapSeasonRepository implements RoadmapSeasonRepository
{
    /** @var array<int, RoadmapSeasonEntity> */
    private array $items = [];
    private int $nextId = 1;

    public function save(RoadmapSeasonEntity $season): void
    {
        if (!is_int($season->getId())) {
            $reflection = new ReflectionClass($season);
            $property = $reflection->getProperty('id');
            $property->setValue($season, $this->nextId++);
        }

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
