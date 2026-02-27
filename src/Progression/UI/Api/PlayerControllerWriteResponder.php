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

use App\Progression\Domain\Entity\PlayerEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class PlayerControllerWriteResponder
{
    public function __construct(
        private readonly PlayerPayloadMapper $playerPayloadMapper,
        private readonly ProgressionApiErrorResponder $progressionApiErrorResponder,
    ) {
    }

    public function invalidPlayerName(): JsonResponse
    {
        return $this->progressionApiErrorResponder->invalidPlayerName();
    }

    public function playerNameAlreadyExists(): JsonResponse
    {
        return $this->progressionApiErrorResponder->playerNameAlreadyExists();
    }

    public function created(PlayerEntity $player): JsonResponse
    {
        return new JsonResponse($this->playerPayloadMapper->map($player), JsonResponse::HTTP_CREATED);
    }

    public function updated(PlayerEntity $player): JsonResponse
    {
        return new JsonResponse($this->playerPayloadMapper->map($player));
    }

    public function deleted(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
