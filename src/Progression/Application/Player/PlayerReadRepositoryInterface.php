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

namespace App\Progression\Application\Player;

use App\Entity\PlayerEntity;
use App\Entity\UserEntity;

interface PlayerReadRepositoryInterface
{
    /**
     * @return list<PlayerEntity>
     */
    public function findByUser(UserEntity $user): array;

    public function findOneByPublicIdAndUser(string $publicId, UserEntity $user): ?PlayerEntity;
}
