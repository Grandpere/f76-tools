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
    public function findOverlappingRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $result = $this->createQueryBuilder('r')
            ->where('r.endsAt >= :from')
            ->andWhere('r.startsAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
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

    public function findLatestCreatedAtBySource(MinervaRotationSourceEnum $source): ?DateTimeImmutable
    {
        $result = $this->createQueryBuilder('r')
            ->where('r.source = :source')
            ->setParameter('source', $source->value)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result instanceof MinervaRotationEntity) {
            return null;
        }

        return $result->getCreatedAt();
    }

    /**
     * @return array{generated: ?DateTimeImmutable, manual: ?DateTimeImmutable}
     */
    public function findLatestCreatedAtSummary(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT source, MAX(created_at) AS latest_created_at
                FROM minerva_rotation
                WHERE source IN (:generated, :manual)
                GROUP BY source
                SQL,
            [
                'generated' => MinervaRotationSourceEnum::GENERATED->value,
                'manual' => MinervaRotationSourceEnum::MANUAL->value,
            ],
        );

        $generated = null;
        $manual = null;

        foreach ($rows as $row) {
            $source = isset($row['source']) && is_string($row['source']) ? $row['source'] : null;
            $rawDate = isset($row['latest_created_at']) && is_string($row['latest_created_at']) ? $row['latest_created_at'] : null;
            if (null === $source || null === $rawDate || '' === trim($rawDate)) {
                continue;
            }

            $parsed = new DateTimeImmutable($rawDate);
            if (MinervaRotationSourceEnum::GENERATED->value === $source) {
                $generated = $parsed;
            } elseif (MinervaRotationSourceEnum::MANUAL->value === $source) {
                $manual = $parsed;
            }
        }

        return [
            'generated' => $generated,
            'manual' => $manual,
        ];
    }
}
