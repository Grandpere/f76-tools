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

use App\Catalog\Application\Roadmap\RoadmapCanonicalEventReadRepository;
use App\Catalog\Application\Roadmap\RoadmapCanonicalEventWriteRepository;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventTranslationEntity;
use App\Catalog\Domain\Entity\RoadmapSeasonEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoadmapCanonicalEventEntity>
 */
final class RoadmapCanonicalEventEntityRepository extends ServiceEntityRepository implements RoadmapCanonicalEventWriteRepository, RoadmapCanonicalEventReadRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoadmapCanonicalEventEntity::class);
    }

    public function clearAll(): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->createQuery(sprintf('DELETE FROM %s t', RoadmapCanonicalEventTranslationEntity::class))->execute();
        $entityManager->createQuery(sprintf('DELETE FROM %s e', RoadmapCanonicalEventEntity::class))->execute();
    }

    public function clearBySeason(RoadmapSeasonEntity $season): void
    {
        $seasonId = $season->getId();
        if (!is_int($seasonId)) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();
        $connection->executeStatement(
            'DELETE FROM roadmap_canonical_event_translation WHERE event_id IN (SELECT id FROM roadmap_canonical_event WHERE season_id = :seasonId)',
            ['seasonId' => $seasonId],
        );
        $connection->executeStatement(
            'DELETE FROM roadmap_canonical_event WHERE season_id = :seasonId',
            ['seasonId' => $seasonId],
        );
    }

    public function saveAll(array $events): void
    {
        $entityManager = $this->getEntityManager();
        foreach ($events as $event) {
            $entityManager->persist($event);
        }
        $entityManager->flush();
    }

    public function findAllOrdered(?RoadmapSeasonEntity $season = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->orderBy('e.startsAt', 'ASC')
            ->addOrderBy('e.sortOrder', 'ASC')
            ->addOrderBy('e.id', 'ASC');

        if ($season instanceof RoadmapSeasonEntity) {
            $qb->andWhere('e.season = :season')->setParameter('season', $season);
        }

        $events = $qb->getQuery()->getResult();

        /** @var list<RoadmapCanonicalEventEntity> $events */
        return $events;
    }
}
