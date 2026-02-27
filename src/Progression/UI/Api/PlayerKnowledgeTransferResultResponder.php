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

final class PlayerKnowledgeTransferResultResponder
{
    /**
     * @param array<string, mixed> $result
     */
    public function respond(array $result): JsonResponse
    {
        $ok = $result['ok'] ?? null;
        if (true !== $ok) {
            return new JsonResponse($result, JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($result);
    }
}

