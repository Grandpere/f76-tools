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

use App\Contract\UserByEmailFinderInterface;
use App\Entity\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEntity>
 */
final class UserEntityRepository extends ServiceEntityRepository implements UserByEmailFinderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEntity::class);
    }

    public function findOneByEmail(string $email): ?UserEntity
    {
        return $this->findOneBy([
            'email' => mb_strtolower(trim($email)),
        ]);
    }

    public function findOneByResetPasswordTokenHash(string $tokenHash): ?UserEntity
    {
        $result = $this->findOneBy([
            'resetPasswordTokenHash' => $tokenHash,
        ]);

        return $result instanceof UserEntity ? $result : null;
    }

    /**
     * @return list<UserEntity>
     */
    public function findAllOrdered(): array
    {
        $result = $this->createQueryBuilder('u')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var list<UserEntity> $result */
        return $result;
    }
}
