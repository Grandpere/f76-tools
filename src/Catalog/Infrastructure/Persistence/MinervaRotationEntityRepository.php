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
use App\Entity\MinervaRotationEntity;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MinervaRotationEntity>
 */
final class MinervaRotationEntityRepository extends ServiceEntityRepository implements MinervaRotationReader
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

    public function deleteOverlappingRange(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $deleted = $this->createQueryBuilder('r')
            ->delete()
            ->where('r.endsAt >= :from')
            ->andWhere('r.startsAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->execute();

        if (is_int($deleted) || is_numeric($deleted)) {
            return (int) $deleted;
        }

        return 0;
    }
}
