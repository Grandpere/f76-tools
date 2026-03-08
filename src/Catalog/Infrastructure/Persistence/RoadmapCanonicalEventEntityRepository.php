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
        $entityManager->createQuery('DELETE FROM App\Catalog\Domain\Entity\RoadmapCanonicalEventTranslationEntity t')->execute();
        $entityManager->createQuery('DELETE FROM App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity e')->execute();
    }

    public function saveAll(array $events): void
    {
        $entityManager = $this->getEntityManager();
        foreach ($events as $event) {
            $entityManager->persist($event);
        }
        $entityManager->flush();
    }

    public function findAllOrdered(): array
    {
        $events = $this->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->orderBy('e.startsAt', 'ASC')
            ->addOrderBy('e.sortOrder', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var list<RoadmapCanonicalEventEntity> $events */
        return $events;
    }
}
