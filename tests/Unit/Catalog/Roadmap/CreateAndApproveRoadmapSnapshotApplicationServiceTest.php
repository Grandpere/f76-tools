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
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
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
        $service = new CreateRoadmapSnapshotApplicationService($repository);

        $imagePath = tempnam(sys_get_temp_dir(), 'roadmap-snapshot-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'test-image');

        $snapshot = $service->create(new CreateRoadmapSnapshotInput(
            'fr',
            $imagePath,
            'ocr.space',
            0.91,
            'Hello world',
        ));

        self::assertSame(RoadmapSnapshotStatusEnum::DRAFT, $snapshot->getStatus());
        self::assertSame('fr', $snapshot->getLocale());
        self::assertNotNull($snapshot->getId());

        @unlink($imagePath);
    }

    public function testApproveSwitchesStatusToApproved(): void
    {
        $repository = new InMemoryRoadmapSnapshotWriteRepository();
        $create = new CreateRoadmapSnapshotApplicationService($repository);
        $approve = new ApproveRoadmapSnapshotApplicationService($repository);

        $imagePath = tempnam(sys_get_temp_dir(), 'roadmap-snapshot-');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, 'test-image');

        $snapshot = $create->create(new CreateRoadmapSnapshotInput(
            'en',
            $imagePath,
            'ocr.space',
            0.92,
            'Hello world',
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

    public function findRecent(int $limit = 20): array
    {
        if ($limit <= 0) {
            return [];
        }

        return array_slice(array_values($this->items), 0, $limit);
    }

    private function forceId(RoadmapSnapshotEntity $snapshot, int $id): void
    {
        $reflection = new ReflectionClass($snapshot);
        $property = $reflection->getProperty('id');
        $property->setValue($snapshot, $id);
    }
}
