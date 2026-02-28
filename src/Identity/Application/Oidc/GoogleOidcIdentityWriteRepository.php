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

use App\Identity\Domain\Entity\UserIdentityEntity;

interface GoogleOidcIdentityWriteRepository
{
    public function save(UserIdentityEntity $identity): void;
}
