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

namespace App\Progression\UI\Api;

use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Application\Player\PlayerReadApplicationService;
use App\Progression\Domain\Entity\PlayerEntity;

final class ProgressionOwnedPlayerReadResolver implements ProgressionOwnedPlayerReadResolverInterface
{
    public function __construct(
        private readonly PlayerReadApplicationService $playerReadApplicationService,
    ) {
    }

    public function resolve(string $playerId, UserEntity $user): ?PlayerEntity
    {
        return $this->playerReadApplicationService->findOwnedByPublicId($user, $playerId);
    }
}
