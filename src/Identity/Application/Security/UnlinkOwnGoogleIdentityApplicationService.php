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

namespace App\Identity\Application\Security;

use App\Identity\Application\Oidc\GoogleOidcIdentityReadRepository;
use App\Identity\Application\Oidc\GoogleOidcIdentityWriteRepository;
use App\Identity\Domain\Entity\UserEntity;

final class UnlinkOwnGoogleIdentityApplicationService
{
    public function __construct(
        private readonly GoogleOidcIdentityReadRepository $googleOidcIdentityReadRepository,
        private readonly GoogleOidcIdentityWriteRepository $googleOidcIdentityWriteRepository,
    ) {
    }

    public function unlink(UserEntity $user): UnlinkOwnGoogleIdentityResult
    {
        $identity = $this->googleOidcIdentityReadRepository->findOneByUserAndProvider($user, 'google');
        if (null === $identity) {
            return UnlinkOwnGoogleIdentityResult::GOOGLE_IDENTITY_NOT_FOUND;
        }

        if (!$user->hasLocalPassword()) {
            return UnlinkOwnGoogleIdentityResult::LOCAL_PASSWORD_REQUIRED;
        }

        $this->googleOidcIdentityWriteRepository->delete($identity);

        return UnlinkOwnGoogleIdentityResult::UNLINKED;
    }
}
