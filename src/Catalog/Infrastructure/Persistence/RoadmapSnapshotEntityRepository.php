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

    public function delete(RoadmapSnapshotEntity $snapshot): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($snapshot);
        $entityManager->flush();
    }

    public function findOneById(int $id): ?RoadmapSnapshotEntity
    {
        $snapshot = $this->find($id);

        return $snapshot instanceof RoadmapSnapshotEntity ? $snapshot : null;
    }

    public function findOneWithEventsById(int $id): ?RoadmapSnapshotEntity
    {
        $snapshot = $this->createQueryBuilder('s')
            ->leftJoin('s.events', 'e')
            ->addSelect('e')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->orderBy('e.sortOrder', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $snapshot instanceof RoadmapSnapshotEntity ? $snapshot : null;
    }

    /**
     * @return list<RoadmapSnapshotEntity>
     */
    public function findRecent(int $limit = 20): array
    {
        $snapshots = $this->createQueryBuilder('s')
            ->orderBy('s.scannedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        /** @var list<RoadmapSnapshotEntity> $snapshots */
        return $snapshots;
    }
}
