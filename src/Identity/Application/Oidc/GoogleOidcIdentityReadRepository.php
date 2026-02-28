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

namespace App\Identity\Application\Oidc;

use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;

interface GoogleOidcIdentityReadRepository
{
    public function findOneByProviderAndProviderUserId(string $provider, string $providerUserId): ?UserIdentityEntity;

    public function findOneByUserAndProvider(UserEntity $user, string $provider): ?UserIdentityEntity;

    /**
     * @param list<int> $userIds
     *
     * @return array<int, UserIdentityEntity>
     */
    public function findGoogleIdentitiesByUserIds(array $userIds): array;
}
