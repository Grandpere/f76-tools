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

use App\Entity\ContactMessageEntity;
use App\Support\Application\Contact\ContactMessageReadRepositoryInterface;
use App\Support\Application\Contact\ContactMessageStatusWriteRepositoryInterface;
use App\Support\Application\Contact\ContactMessageWriter;
use App\Support\Domain\Contact\ContactMessageStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessageEntity>
 */
final class ContactMessageEntityRepository extends ServiceEntityRepository implements ContactMessageWriter, ContactMessageStatusWriteRepositoryInterface, ContactMessageReadRepositoryInterface
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
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC');

        if ($status instanceof ContactMessageStatusEnum) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        if ('' !== $query) {
            $needle = '%'.mb_strtolower($query).'%';
            $qb->andWhere('LOWER(c.email) LIKE :needle OR LOWER(c.subject) LIKE :needle OR LOWER(c.message) LIKE :needle')
                ->setParameter('needle', $needle);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(c.id)')
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

        /** @var list<ContactMessageEntity> $rows */
        $rows = array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof ContactMessageEntity));

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }
}
