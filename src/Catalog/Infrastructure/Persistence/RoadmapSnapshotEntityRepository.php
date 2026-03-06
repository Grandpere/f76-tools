<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoadmapSnapshotEntity>
 */
final class RoadmapSnapshotEntityRepository extends ServiceEntityRepository implements RoadmapSnapshotWriteRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoadmapSnapshotEntity::class);
    }

    public function save(RoadmapSnapshotEntity $snapshot): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($snapshot);
        $entityManager->flush();
    }

    public function findOneById(int $id): ?RoadmapSnapshotEntity
    {
        $snapshot = $this->find($id);

        return $snapshot instanceof RoadmapSnapshotEntity ? $snapshot : null;
    }
}

