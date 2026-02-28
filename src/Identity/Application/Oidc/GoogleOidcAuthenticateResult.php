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

final class GoogleOidcAuthenticateResult
{
    public function __construct(
        private readonly UserEntity $user,
        private readonly GoogleOidcAuthenticationAction $action,
    ) {
    }

    public function user(): UserEntity
    {
        return $this->user;
    }

    public function action(): GoogleOidcAuthenticationAction
    {
        return $this->action;
    }
}
