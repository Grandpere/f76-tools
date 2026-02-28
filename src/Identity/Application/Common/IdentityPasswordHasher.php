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

namespace App\Identity\Application\Common;

use App\Identity\Domain\Entity\UserEntity;

interface IdentityPasswordHasher
{
    public function hash(UserEntity $user, string $plainPassword): string;
}
