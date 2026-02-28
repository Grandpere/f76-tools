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

use RuntimeException;

final class GoogleOidcAuthenticationException extends RuntimeException
{
    public function __construct(
        private readonly string $flashMessageKey,
    ) {
        parent::__construct($flashMessageKey);
    }

    public function flashMessageKey(): string
    {
        return $this->flashMessageKey;
    }
}
