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

use App\Identity\Application\ForgotPassword\ForgotPasswordUserRepository;
use App\Identity\Application\Registration\RegistrationUserRepository;
use App\Identity\Application\ResendVerification\ResendVerificationUserRepository;
use App\Identity\Application\ResetPassword\ResetPasswordUserRepository;
use App\Identity\Application\User\UserByEmailFinder;
use App\Identity\Application\VerifyEmail\VerifyEmailUserRepository;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;
use App\Support\Application\AdminUser\AdminUserListCriteria;
use App\Support\Application\AdminUser\AdminUserManagementReadRepository;
use App\Support\Application\AdminUser\AdminUserManagementWriteRepository;
use App\Support\Application\AdminUser\AdminUserSummary;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEntity>
 */
final class UserEntityRepository extends ServiceEntityRepository implements UserByEmailFinder, VerifyEmailUserRepository, ResetPasswordUserRepository, ForgotPasswordUserRepository, RegistrationUserRepository, ResendVerificationUserRepository, AdminUserManagementReadRepository, AdminUserManagementWriteRepository
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
    public function findByAdminCriteria(AdminUserListCriteria $criteria): array
    {
        $qb = $this->createAdminCriteriaQueryBuilder($criteria);
        $this->applyAdminSort($qb, $criteria);
        $qb->setFirstResult($criteria->offset());
        $qb->setMaxResults($criteria->perPage);

        $result = $qb->getQuery()->getResult();

        /** @var list<UserEntity> $result */
        return $result;
    }

    /**
     * @return list<UserEntity>
     */
    public function findAllByAdminCriteria(AdminUserListCriteria $criteria): array
    {
        $qb = $this->createAdminCriteriaQueryBuilder($criteria);
        $this->applyAdminSort($qb, $criteria);

        $result = $qb->getQuery()->getResult();

        /** @var list<UserEntity> $result */
        return $result;
    }

    public function countByAdminCriteria(AdminUserListCriteria $criteria): int
    {
        $qb = $this->createAdminCriteriaQueryBuilder($criteria)
            ->select('COUNT(u.id)');

        /** @var int|string $count */
        $count = $qb->getQuery()->getSingleScalarResult();

        return (int) $count;
    }

    public function getAdminSummary(): AdminUserSummary
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(u.id) AS total_users,
                COUNT(ui.id) AS google_linked_users
            FROM app_user u
            LEFT JOIN user_identity ui
                ON ui.user_id = u.id
                AND ui.provider = :provider
            SQL;

        /** @var array{total_users: int|string, google_linked_users: int|string} $row */
        $row = $this->getEntityManager()->getConnection()->fetchAssociative($sql, [
            'provider' => 'google',
        ]);

        return new AdminUserSummary(
            (int) $row['total_users'],
            (int) $row['google_linked_users'],
        );
    }

    public function save(UserEntity $user): void
    {
        $this->getEntityManager()->persist($user);
    }

    private function createAdminCriteriaQueryBuilder(AdminUserListCriteria $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');

        $needsGoogleJoin = '' !== $criteria->googleFilter;
        if ($needsGoogleJoin) {
            $qb->leftJoin(UserIdentityEntity::class, 'ui', 'WITH', 'ui.user = u AND ui.provider = :googleProvider');
            $qb->setParameter('googleProvider', 'google');
        }

        if ('active' === $criteria->activeFilter) {
            $qb->andWhere('u.isActive = :isActive')->setParameter('isActive', true);
        } elseif ('inactive' === $criteria->activeFilter) {
            $qb->andWhere('u.isActive = :isActive')->setParameter('isActive', false);
        }

        if ('linked' === $criteria->googleFilter) {
            $qb->andWhere('ui.id IS NOT NULL');
        } elseif ('unlinked' === $criteria->googleFilter) {
            $qb->andWhere('ui.id IS NULL');
        }

        if ('admin' === $criteria->roleFilter) {
            $qb->andWhere('u.isAdmin = :isAdmin')->setParameter('isAdmin', true);
        } elseif ('user' === $criteria->roleFilter) {
            $qb->andWhere('u.isAdmin = :isAdmin')->setParameter('isAdmin', false);
        }

        if ('verified' === $criteria->verifiedFilter) {
            $qb->andWhere('u.isEmailVerified = :isEmailVerified')->setParameter('isEmailVerified', true);
        } elseif ('unverified' === $criteria->verifiedFilter) {
            $qb->andWhere('u.isEmailVerified = :isEmailVerified')->setParameter('isEmailVerified', false);
        }

        if ('enabled' === $criteria->localPasswordFilter) {
            $qb->andWhere('u.hasLocalPassword = :hasLocalPassword')->setParameter('hasLocalPassword', true);
        } elseif ('disabled' === $criteria->localPasswordFilter) {
            $qb->andWhere('u.hasLocalPassword = :hasLocalPassword')->setParameter('hasLocalPassword', false);
        }

        if ($criteria->createdFrom instanceof DateTimeImmutable) {
            $qb->andWhere('u.createdAt >= :createdFrom')->setParameter('createdFrom', $criteria->createdFrom);
        }
        if ($criteria->createdTo instanceof DateTimeImmutable) {
            $qb->andWhere('u.createdAt <= :createdTo')->setParameter('createdTo', $criteria->createdTo);
        }

        if ('' !== $criteria->query) {
            $qb->andWhere('LOWER(u.email) LIKE :q')->setParameter('q', '%'.mb_strtolower($criteria->query).'%');
        }

        return $qb;
    }

    private function applyAdminSort(QueryBuilder $qb, AdminUserListCriteria $criteria): void
    {
        $direction = 'desc' === $criteria->dir ? 'DESC' : 'ASC';

        $sortField = match ($criteria->sort) {
            'createdat' => 'u.createdAt',
            'active' => 'u.isActive',
            default => 'u.email',
        };

        $qb->orderBy($sortField, $direction)
            ->addOrderBy('u.email', 'ASC');
    }
}
