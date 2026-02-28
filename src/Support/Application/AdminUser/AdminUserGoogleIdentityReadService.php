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

namespace App\Support\Application\AdminUser;

use App\Identity\Application\Oidc\GoogleOidcIdentityReadRepository;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;

final class AdminUserGoogleIdentityReadService
{
    public function __construct(
        private readonly GoogleOidcIdentityReadRepository $identityReadRepository,
    ) {
    }

    /**
     * @param list<UserEntity> $users
     *
     * @return array<int, UserIdentityEntity>
     */
    public function getGoogleIdentityByUserId(array $users): array
    {
        $userIds = [];
        foreach ($users as $user) {
            $userId = $user->getId();
            if (!is_int($userId)) {
                continue;
            }
            $userIds[] = $userId;
        }

        return $this->identityReadRepository->findGoogleIdentitiesByUserIds($userIds);
    }
}
