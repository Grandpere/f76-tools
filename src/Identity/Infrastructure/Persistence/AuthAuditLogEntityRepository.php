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

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Application\Security\AuthAuditLogReader;
use App\Identity\Application\Security\AuthAuditLogView;
use App\Identity\Domain\Entity\AuthAuditLogEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuthAuditLogEntity>
 */
final class AuthAuditLogEntityRepository extends ServiceEntityRepository implements AuthAuditLogReader
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthAuditLogEntity::class);
    }

    /**
     * @return list<AuthAuditLogView>
     */
    public function findLatestByUserId(int $userId, int $limit): array
    {
        return $this->findByUserIdWithFilters($userId, $limit, '', '');
    }

    /**
     * @return list<AuthAuditLogView>
     */
    public function findByUserIdWithFilters(int $userId, int $limit, string $levelFilter, string $query): array
    {
        $effectiveLimit = max(1, min($limit, 100));
        $builder = $this->createQueryBuilder('log')
            ->andWhere('IDENTITY(log.user) = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('log.occurredAt', 'DESC');

        $normalizedLevel = mb_strtolower(trim($levelFilter));
        if (in_array($normalizedLevel, ['info', 'warning'], true)) {
            $builder
                ->andWhere('log.level = :level')
                ->setParameter('level', $normalizedLevel);
        }

        $normalizedQuery = trim($query);
        if ('' !== $normalizedQuery) {
            $builder
                ->andWhere('log.event LIKE :q OR log.clientIp LIKE :q')
                ->setParameter('q', '%'.$normalizedQuery.'%');
        }

        /** @var list<AuthAuditLogEntity> $rows */
        $rows = $builder
            ->setMaxResults($effectiveLimit)
            ->getQuery()
            ->getResult();

        $events = [];
        foreach ($rows as $row) {
            $events[] = new AuthAuditLogView(
                occurredAt: $row->getOccurredAt(),
                event: $row->getEvent(),
                level: $row->getLevel(),
                clientIp: $row->getClientIp(),
                context: $row->getContext() ?? [],
            );
        }

        return $events;
    }

    public function add(AuthAuditLogEntity $entity): void
    {
        $this->getEntityManager()->persist($entity);
    }
}
