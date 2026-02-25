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

namespace App\Repository;

use App\Entity\AdminAuditLogEntity;
use App\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminAuditLogEntity>
 */
final class AdminAuditLogEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminAuditLogEntity::class);
    }

    /**
     * @return array{rows: list<AdminAuditLogEntity>, total: int}
     */
    public function findPaginated(string $query, string $action, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.actorUser', 'actor')
            ->leftJoin('a.targetUser', 'target')
            ->addSelect('actor')
            ->addSelect('target')
            ->orderBy('a.occurredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if ('' !== $action) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ('' !== $query) {
            $needle = '%'.mb_strtolower($query).'%';
            $qb->andWhere('LOWER(actor.email) LIKE :needle OR LOWER(target.email) LIKE :needle OR LOWER(a.action) LIKE :needle')
                ->setParameter('needle', $needle);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(a.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $offset = max(0, ($page - 1) * $perPage);
        $result = $qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        if (!is_array($result)) {
            $result = [];
        }

        /** @var list<AdminAuditLogEntity> $rows */
        $rows = array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof AdminAuditLogEntity));

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * @return list<string>
     */
    public function findDistinctActions(): array
    {
        $raw = $this->createQueryBuilder('a')
            ->select('DISTINCT a.action')
            ->orderBy('a.action', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        /** @var list<string> $actions */
        $actions = array_values(array_filter($raw, static fn (mixed $value): bool => is_string($value) && '' !== trim($value)));

        return $actions;
    }

    /**
     * @param list<string> $actions
     */
    public function countRecentActionsByActor(UserEntity $actor, array $actions, DateTimeImmutable $since): int
    {
        if ([] === $actions) {
            return 0;
        }

        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.actorUser = :actor')
            ->andWhere('a.action IN (:actions)')
            ->andWhere('a.occurredAt >= :since')
            ->setParameter('actor', $actor)
            ->setParameter('actions', $actions)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
