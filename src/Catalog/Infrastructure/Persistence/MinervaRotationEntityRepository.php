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

use App\Catalog\Application\Minerva\MinervaRotationReader;
use App\Catalog\Application\Minerva\MinervaRotationRegenerationRepository;
use App\Catalog\Domain\Entity\MinervaRotationEntity;
use App\Catalog\Domain\Minerva\MinervaRotationSourceEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MinervaRotationEntity>
 */
final class MinervaRotationEntityRepository extends ServiceEntityRepository implements MinervaRotationReader, MinervaRotationRegenerationRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MinervaRotationEntity::class);
    }

    /**
     * @return list<MinervaRotationEntity>
     */
    public function findAllOrdered(): array
    {
        $result = $this->createQueryBuilder('r')
            ->orderBy('r.startsAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
        if (!is_array($result)) {
            return [];
        }

        /** @var list<MinervaRotationEntity> $rows */
        $rows = array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof MinervaRotationEntity));

        return $rows;
    }

    public function deleteOverlappingGeneratedRange(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $deleted = $this->createQueryBuilder('r')
            ->delete()
            ->where('r.endsAt >= :from')
            ->andWhere('r.startsAt <= :to')
            ->andWhere('r.source = :source')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('source', MinervaRotationSourceEnum::GENERATED->value)
            ->getQuery()
            ->execute();

        if (is_int($deleted) || is_numeric($deleted)) {
            return (int) $deleted;
        }

        return 0;
    }

    /**
     * @return list<MinervaRotationEntity>
     */
    public function findManualOverlappingRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $result = $this->createQueryBuilder('r')
            ->where('r.endsAt >= :from')
            ->andWhere('r.startsAt <= :to')
            ->andWhere('r.source = :source')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('source', MinervaRotationSourceEnum::MANUAL->value)
            ->orderBy('r.startsAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
        if (!is_array($result)) {
            return [];
        }

        /** @var list<MinervaRotationEntity> $rows */
        $rows = array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof MinervaRotationEntity));

        return $rows;
    }

    /**
     * @return list<MinervaRotationEntity>
     */
    public function findManualOrdered(): array
    {
        $result = $this->createQueryBuilder('r')
            ->where('r.source = :source')
            ->setParameter('source', MinervaRotationSourceEnum::MANUAL->value)
            ->orderBy('r.startsAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
        if (!is_array($result)) {
            return [];
        }

        /** @var list<MinervaRotationEntity> $rows */
        $rows = array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof MinervaRotationEntity));

        return $rows;
    }

    public function findManualById(int $id): ?MinervaRotationEntity
    {
        $result = $this->createQueryBuilder('r')
            ->where('r.id = :id')
            ->andWhere('r.source = :source')
            ->setParameter('id', $id)
            ->setParameter('source', MinervaRotationSourceEnum::MANUAL->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof MinervaRotationEntity ? $result : null;
    }
}
