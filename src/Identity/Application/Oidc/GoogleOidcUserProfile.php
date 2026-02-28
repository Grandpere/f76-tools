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

final class GoogleOidcUserProfile
{
    public function __construct(
        private readonly string $providerUserId,
        private readonly string $email,
        private readonly bool $emailVerified,
    ) {
    }

    public function providerUserId(): string
    {
        return $this->providerUserId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function emailVerified(): bool
    {
        return $this->emailVerified;
    }
}
