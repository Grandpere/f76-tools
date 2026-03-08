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

use App\Support\Application\Contact\ContactMessageReadRepository;
use App\Support\Application\Contact\ContactMessageStatusWriteRepository;
use App\Support\Application\Contact\ContactMessageWriter;
use App\Support\Domain\Contact\ContactMessageStatusEnum;
use App\Support\Domain\Entity\ContactMessageEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessageEntity>
 */
final class ContactMessageEntityRepository extends ServiceEntityRepository implements ContactMessageWriter, ContactMessageStatusWriteRepository, ContactMessageReadRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessageEntity::class);
    }

    public function save(ContactMessageEntity $message): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($message);
        $entityManager->flush();
    }

    public function getById(int $id): ?ContactMessageEntity
    {
        $message = $this->find($id);

        return $message instanceof ContactMessageEntity ? $message : null;
    }

    /**
     * @return array{rows: list<ContactMessageEntity>, total: int}
     */
    public function findPaginated(string $query, ?ContactMessageStatusEnum $status, int $page, int $perPage): array
    {
        $qb = $this->createFilteredQueryBuilder($query, $status, true);
        $countQb = $this->createFilteredQueryBuilder($query, $status, false);

        $total = (int) $countQb
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $result = $this->executeRowsPageQuery($qb, $page, $perPage);
        if (!is_array($result)) {
            $result = [];
        }

        /** @var list<ContactMessageEntity> $rows */
        $rows = array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof ContactMessageEntity));

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * @return list<ContactMessageEntity>
     */
    public function findRowsPage(string $query, ?ContactMessageStatusEnum $status, int $page, int $perPage): array
    {
        $result = $this->executeRowsPageQuery($this->createFilteredQueryBuilder($query, $status, true), $page, $perPage);

        if (!is_array($result)) {
            return [];
        }

        /** @var list<ContactMessageEntity> $rows */
        $rows = array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof ContactMessageEntity));

        return $rows;
    }

    private function createFilteredQueryBuilder(string $query, ?ContactMessageStatusEnum $status, bool $withOrderBy): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        if ($status instanceof ContactMessageStatusEnum) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        if ('' !== $query) {
            $needle = '%'.mb_strtolower($query).'%';
            $qb->andWhere('LOWER(c.email) LIKE :needle OR LOWER(c.subject) LIKE :needle OR LOWER(c.message) LIKE :needle')
                ->setParameter('needle', $needle);
        }

        if ($withOrderBy) {
            $qb->orderBy('c.createdAt', 'DESC')
                ->addOrderBy('c.id', 'DESC');
        }

        return $qb;
    }

    private function executeRowsPageQuery(\Doctrine\ORM\QueryBuilder $qb, int $page, int $perPage): mixed
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }
}
