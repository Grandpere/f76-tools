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

final class ProgressionApiErrorResponder
{
    public function playerNotFound(): JsonResponse
    {
        return new JsonResponse(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
    }

    public function itemNotFound(): JsonResponse
    {
        return new JsonResponse(['error' => 'Item not found.'], JsonResponse::HTTP_NOT_FOUND);
    }

    public function invalidItemType(): JsonResponse
    {
        return new JsonResponse(['error' => 'Invalid item type.'], JsonResponse::HTTP_BAD_REQUEST);
    }

    public function invalidPlayerName(): JsonResponse
    {
        return new JsonResponse(['error' => 'Invalid player name.'], JsonResponse::HTTP_BAD_REQUEST);
    }

    public function playerNameAlreadyExists(): JsonResponse
    {
        return new JsonResponse(['error' => 'Player name already exists.'], JsonResponse::HTTP_CONFLICT);
    }
}
