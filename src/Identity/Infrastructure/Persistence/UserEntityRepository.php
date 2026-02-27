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

use App\Entity\UserEntity;
use App\Identity\Application\ForgotPassword\ForgotPasswordUserRepositoryInterface;
use App\Identity\Application\Registration\RegistrationUserRepositoryInterface;
use App\Identity\Application\ResendVerification\ResendVerificationUserRepositoryInterface;
use App\Identity\Application\ResetPassword\ResetPasswordUserRepositoryInterface;
use App\Identity\Application\User\UserByEmailFinder;
use App\Identity\Application\VerifyEmail\VerifyEmailUserRepositoryInterface;
use App\Support\Application\AdminUser\AdminUserManagementReadRepositoryInterface;
use App\Support\Application\AdminUser\AdminUserManagementWriteRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEntity>
 */
final class UserEntityRepository extends ServiceEntityRepository implements UserByEmailFinder, VerifyEmailUserRepositoryInterface, ResetPasswordUserRepositoryInterface, ForgotPasswordUserRepositoryInterface, RegistrationUserRepositoryInterface, ResendVerificationUserRepositoryInterface, AdminUserManagementReadRepositoryInterface, AdminUserManagementWriteRepositoryInterface
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

    public function getById(int $id): ?UserEntity
    {
        $result = $this->find($id);

        return $result instanceof UserEntity ? $result : null;
    }

    public function findOneByResetPasswordTokenHash(string $tokenHash): ?UserEntity
    {
        $result = $this->findOneBy([
            'resetPasswordTokenHash' => $tokenHash,
        ]);

        return $result instanceof UserEntity ? $result : null;
    }

    public function findOneByEmailVerificationTokenHash(string $tokenHash): ?UserEntity
    {
        $result = $this->findOneBy([
            'emailVerificationTokenHash' => $tokenHash,
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

    public function save(UserEntity $user): void
    {
        $this->getEntityManager()->persist($user);
    }
}
