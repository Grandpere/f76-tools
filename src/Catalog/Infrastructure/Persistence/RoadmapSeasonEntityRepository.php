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

use App\Catalog\Application\Roadmap\RoadmapSeasonRepository;
use App\Catalog\Domain\Entity\RoadmapSeasonEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoadmapSeasonEntity>
 */
final class RoadmapSeasonEntityRepository extends ServiceEntityRepository implements RoadmapSeasonRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoadmapSeasonEntity::class);
    }

    public function save(RoadmapSeasonEntity $season): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($season);
        $entityManager->flush();
    }

    public function findOneById(int $id): ?RoadmapSeasonEntity
    {
        $season = $this->find($id);

        return $season instanceof RoadmapSeasonEntity ? $season : null;
    }

    public function findOneBySeasonNumber(int $seasonNumber): ?RoadmapSeasonEntity
    {
        $season = $this->findOneBy(['seasonNumber' => $seasonNumber]);

        return $season instanceof RoadmapSeasonEntity ? $season : null;
    }

    public function findActive(): ?RoadmapSeasonEntity
    {
        $season = $this->findOneBy(['isActive' => true], ['seasonNumber' => 'DESC', 'id' => 'DESC']);

        return $season instanceof RoadmapSeasonEntity ? $season : null;
    }

    public function findAllOrderedBySeasonNumberDesc(): array
    {
        $seasons = $this->createQueryBuilder('s')
            ->orderBy('s.seasonNumber', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<RoadmapSeasonEntity> $seasons */
        return $seasons;
    }

    public function deactivateAllExcept(?RoadmapSeasonEntity $activeSeason): void
    {
        $entityManager = $this->getEntityManager();
        $qb = $entityManager->createQueryBuilder()
            ->update(RoadmapSeasonEntity::class, 's')
            ->set('s.isActive', ':inactive')
            ->setParameter('inactive', false);

        if ($activeSeason instanceof RoadmapSeasonEntity && is_int($activeSeason->getId())) {
            $qb->where('s.id != :activeId')->setParameter('activeId', $activeSeason->getId());
        }

        $qb->getQuery()->execute();
    }
}
