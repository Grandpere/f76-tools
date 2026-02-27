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

namespace App\Support\UI\Admin;

use App\Entity\UserEntity;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AdminAuthenticatedUserContext
{
    public function requireAuthenticatedUser(mixed $user): UserEntity
    {
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        return $user;
    }
}
