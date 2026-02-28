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

namespace App\Support\Infrastructure\Persistence;

use App\Identity\Domain\Entity\UserEntity;
use App\Support\Application\Admin\Audit\AdminAuditLogPurger;
use App\Support\Application\AdminUser\AdminUserAuditReadRepository;
use App\Support\Application\Audit\AuditLogReadRepository;
use App\Support\Domain\Entity\AdminAuditLogEntity;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

/**
 * @extends ServiceEntityRepository<AdminAuditLogEntity>
 */
final class AdminAuditLogEntityRepository extends ServiceEntityRepository implements AdminAuditLogPurger, AuditLogReadRepository, AdminUserAuditReadRepository
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
     * @return list<AdminAuditLogEntity>
     */
    public function findForExport(string $query, string $action, int $maxRows = 10000): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.actorUser', 'actor')
            ->leftJoin('a.targetUser', 'target')
            ->addSelect('actor')
            ->addSelect('target')
            ->orderBy('a.occurredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults(max(1, $maxRows));

        if ('' !== $action) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ('' !== $query) {
            $needle = '%'.mb_strtolower($query).'%';
            $qb->andWhere('LOWER(actor.email) LIKE :needle OR LOWER(target.email) LIKE :needle OR LOWER(a.action) LIKE :needle')
                ->setParameter('needle', $needle);
        }

        $result = $qb->getQuery()->getResult();
        if (!is_array($result)) {
            return [];
        }

        /** @var list<AdminAuditLogEntity> $rows */
        $rows = array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof AdminAuditLogEntity));

        return $rows;
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

    public function countOlderThan(DateTimeImmutable $cutoff): int
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.occurredAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        $result = $this->createQueryBuilder('a')
            ->delete()
            ->where('a.occurredAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        if (!is_int($result)) {
            throw new RuntimeException('Unexpected delete result type for admin audit log purge.');
        }

        return $result;
    }
}
