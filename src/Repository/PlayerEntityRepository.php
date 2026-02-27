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

use App\Contract\PlayerByOwnerFinderInterface;
use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\Application\Player\PlayerReadRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlayerEntity>
 */
final class PlayerEntityRepository extends ServiceEntityRepository implements PlayerByOwnerFinderInterface, PlayerReadRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayerEntity::class);
    }

    /**
     * @return list<PlayerEntity>
     */
    public function findByUser(UserEntity $user): array
    {
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var list<PlayerEntity> $result */
        return $result;
    }

    public function findOneByPublicIdAndUser(string $publicId, UserEntity $user): ?PlayerEntity
    {
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.publicId = :publicId')
            ->andWhere('p.user = :user')
            ->setParameter('publicId', $publicId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof PlayerEntity ? $result : null;
    }
}
