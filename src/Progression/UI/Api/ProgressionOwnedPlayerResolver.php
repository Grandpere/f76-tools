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
use App\Progression\Application\Knowledge\OwnedPlayerResolverInterface;

final class ProgressionOwnedPlayerResolver
{
    public function __construct(
        private readonly ProgressionApiUserContext $progressionApiUserContext,
        private readonly OwnedPlayerResolverInterface $ownedPlayerResolver,
    ) {
    }

    public function resolve(string $playerId, mixed $user): ?PlayerEntity
    {
        $authenticatedUser = $this->progressionApiUserContext->requireAuthenticatedUser($user);

        return $this->ownedPlayerResolver->resolveOwnedPlayer($authenticatedUser, $playerId);
    }
}
