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

use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use Symfony\Component\HttpFoundation\JsonResponse;

trait ProgressionApiResolverHelpersTrait
{
    abstract protected function getUser(): mixed;

    protected function resolveOwnedPlayerOrNotFound(
        ProgressionOwnedPlayerApiResolver $progressionOwnedPlayerApiResolver,
        string $playerId,
    ): PlayerEntity|JsonResponse {
        return $progressionOwnedPlayerApiResolver->resolveOrNotFound($playerId, $this->getUser());
    }

    protected function resolveItemOrNotFound(
        ProgressionItemApiResolver $progressionItemApiResolver,
        string $itemId,
    ): ItemEntity|JsonResponse {
        return $progressionItemApiResolver->resolveOrNotFound($itemId);
    }
}
