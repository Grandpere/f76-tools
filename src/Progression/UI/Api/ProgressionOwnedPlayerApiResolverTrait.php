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

trait ProgressionOwnedPlayerApiResolverTrait
{
    abstract protected function getUser(): mixed;

    abstract protected function progressionOwnedPlayerApiResolver(): ProgressionOwnedPlayerApiResolver;

    protected function resolveOwnedPlayerOrNotFound(string $playerId): PlayerEntity|JsonResponse
    {
        return $this->progressionOwnedPlayerApiResolver()->resolveOrNotFound($playerId, $this->getUser());
    }
}
