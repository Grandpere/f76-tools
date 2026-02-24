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

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemEntity>
 */
final class ItemEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemEntity::class);
    }

    public function findOneByTypeAndSourceId(ItemTypeEnum $type, int $sourceId): ?ItemEntity
    {
        return $this->findOneBy([
            'type' => $type,
            'sourceId' => $sourceId,
        ]);
    }

    public function findOneById(int $id): ?ItemEntity
    {
        $item = $this->find($id);

        return $item instanceof ItemEntity ? $item : null;
    }

    /**
     * @return list<ItemEntity>
     */
    public function findAllOrdered(?ItemTypeEnum $type = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.type', 'ASC')
            ->addOrderBy('i.sourceId', 'ASC');

        if (null !== $type) {
            $qb->andWhere('i.type = :type')
                ->setParameter('type', $type);
        }

        $items = $qb->getQuery()->getResult();

        /** @var list<ItemEntity> $items */
        return $items;
    }
}
