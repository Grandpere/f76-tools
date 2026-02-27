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

use Symfony\Component\HttpFoundation\JsonResponse;

final class PlayerItemActionContextResolver
{
    public function __construct(
        private readonly ProgressionOwnedPlayerApiResolver $progressionOwnedPlayerApiResolver,
        private readonly ProgressionItemApiResolver $progressionItemApiResolver,
    ) {
    }

    public function resolveOrNotFound(string $playerId, string $itemId, mixed $user): PlayerItemActionContext|JsonResponse
    {
        $player = $this->progressionOwnedPlayerApiResolver->resolveOrNotFound($playerId, $user);
        if ($player instanceof JsonResponse) {
            return $player;
        }

        $item = $this->progressionItemApiResolver->resolveOrNotFound($itemId);
        if ($item instanceof JsonResponse) {
            return $item;
        }

        return new PlayerItemActionContext($player, $item);
    }
}
