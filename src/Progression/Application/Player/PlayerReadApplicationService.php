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

use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Domain\Entity\PlayerEntity;

final class PlayerReadApplicationService
{
    public function __construct(
        private readonly PlayerReadRepositoryInterface $playerRepository,
    ) {
    }

    /**
     * @return list<PlayerEntity>
     */
    public function listForUser(UserEntity $user): array
    {
        return $this->playerRepository->findByUser($user);
    }

    public function findOwnedByPublicId(UserEntity $user, string $publicId): ?PlayerEntity
    {
        return $this->playerRepository->findOneByPublicIdAndUser($publicId, $user);
    }
}
