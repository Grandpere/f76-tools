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

use App\Entity\PlayerEntity;
use Symfony\Component\HttpFoundation\JsonResponse;

final class PlayerOwnedContextResolver
{
    public function __construct(
        private readonly ProgressionApiUserContext $progressionApiUserContext,
        private readonly ProgressionOwnedPlayerReadResolverInterface $progressionOwnedPlayerReadResolver,
        private readonly ProgressionApiErrorResponder $progressionApiErrorResponder,
    ) {
    }

    public function resolveOrNotFound(string $playerId, mixed $user): PlayerEntity|JsonResponse
    {
        $authenticatedUser = $this->progressionApiUserContext->requireAuthenticatedUser($user);
        $player = $this->progressionOwnedPlayerReadResolver->resolve($playerId, $authenticatedUser);
        if (null === $player) {
            return $this->progressionApiErrorResponder->playerNotFound();
        }

        return $player;
    }
}
