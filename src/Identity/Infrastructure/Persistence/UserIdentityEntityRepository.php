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

use App\Identity\Application\Oidc\GoogleOidcIdentityReadRepository;
use App\Identity\Application\Oidc\GoogleOidcIdentityWriteRepository;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserIdentityEntity>
 */
final class UserIdentityEntityRepository extends ServiceEntityRepository implements GoogleOidcIdentityReadRepository, GoogleOidcIdentityWriteRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserIdentityEntity::class);
    }

    public function findOneByProviderAndProviderUserId(string $provider, string $providerUserId): ?UserIdentityEntity
    {
        $result = $this->findOneBy([
            'provider' => mb_strtolower(trim($provider)),
            'providerUserId' => trim($providerUserId),
        ]);

        return $result instanceof UserIdentityEntity ? $result : null;
    }

    public function findOneByUserAndProvider(UserEntity $user, string $provider): ?UserIdentityEntity
    {
        $result = $this->findOneBy([
            'user' => $user,
            'provider' => mb_strtolower(trim($provider)),
        ]);

        return $result instanceof UserIdentityEntity ? $result : null;
    }

    public function save(UserIdentityEntity $identity): void
    {
        $this->getEntityManager()->persist($identity);
    }

    public function delete(UserIdentityEntity $identity): void
    {
        $this->getEntityManager()->remove($identity);
    }
}
