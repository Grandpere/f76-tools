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
use App\Progression\Application\Player\PlayerReadApplicationService;

final class ProgressionOwnedPlayerReadResolver
{
    public function __construct(
        private readonly ProgressionApiUserContext $progressionApiUserContext,
        private readonly PlayerReadApplicationService $playerReadApplicationService,
    ) {
    }

    public function resolve(string $playerId, mixed $user): ?PlayerEntity
    {
        $authenticatedUser = $this->progressionApiUserContext->requireAuthenticatedUser($user);

        return $this->playerReadApplicationService->findOwnedByPublicId($authenticatedUser, $playerId);
    }
}
