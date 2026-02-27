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
use Symfony\Component\HttpFoundation\Request;

final class PlayerKnowledgeImportContextResolver
{
    public function __construct(
        private readonly ProgressionOwnedPlayerApiResolver $progressionOwnedPlayerApiResolver,
        private readonly ProgressionApiJsonPayloadDecoder $progressionApiJsonPayloadDecoder,
    ) {
    }

    public function resolveOrNotFound(string $playerId, Request $request, mixed $user): PlayerKnowledgeImportContext|JsonResponse
    {
        $player = $this->progressionOwnedPlayerApiResolver->resolveOrNotFound($playerId, $user);
        if ($player instanceof JsonResponse) {
            return $player;
        }

        return new PlayerKnowledgeImportContext($player, $this->progressionApiJsonPayloadDecoder->decode($request));
    }
}
