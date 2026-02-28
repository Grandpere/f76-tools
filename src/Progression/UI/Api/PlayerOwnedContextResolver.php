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
use App\Progression\Domain\Entity\PlayerEntity;
use Symfony\Component\HttpFoundation\JsonResponse;

final class PlayerOwnedContextResolver
{
    public function __construct(
        private readonly ProgressionOwnedPlayerReadPort $progressionOwnedPlayerReadResolver,
        private readonly ProgressionApiErrorResponder $progressionApiErrorResponder,
    ) {
    }

    public function resolveOrNotFound(string $playerId, UserEntity $user): PlayerEntity|JsonResponse
    {
        $player = $this->progressionOwnedPlayerReadResolver->resolve($playerId, $user);
        if (null === $player) {
            return $this->progressionApiErrorResponder->playerNotFound();
        }

        return $player;
    }
}
