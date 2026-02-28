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
use App\Identity\Application\Oidc\GoogleOidcIdentityWriteRepository;
use App\Identity\Domain\Entity\UserEntity;

final class UnlinkGoogleIdentityApplicationService
{
    public function __construct(
        private readonly AdminUserManagementWriteRepositoryInterface $userRepository,
        private readonly GoogleOidcIdentityReadRepository $googleOidcIdentityReadRepository,
        private readonly GoogleOidcIdentityWriteRepository $googleOidcIdentityWriteRepository,
    ) {
    }

    public function unlink(int $targetUserId): UnlinkGoogleIdentityResult
    {
        $target = $this->userRepository->getById($targetUserId);
        if (null === $target) {
            return UnlinkGoogleIdentityResult::USER_NOT_FOUND;
        }

        $identity = $this->googleOidcIdentityReadRepository->findOneByUserAndProvider($target, 'google');
        if (null === $identity) {
            return UnlinkGoogleIdentityResult::GOOGLE_IDENTITY_NOT_FOUND;
        }

        $this->googleOidcIdentityWriteRepository->delete($identity);

        return UnlinkGoogleIdentityResult::UNLINKED;
    }

    public function hasGoogleIdentity(UserEntity $user): bool
    {
        return null !== $this->googleOidcIdentityReadRepository->findOneByUserAndProvider($user, 'google');
    }
}
