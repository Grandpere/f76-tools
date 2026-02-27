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

final class PlayerNameApiResolver
{
    public function __construct(
        private readonly PlayerNameRequestExtractor $playerNameRequestExtractor,
        private readonly PlayerControllerWriteResponder $playerControllerWriteResponder,
    ) {
    }

    public function resolveOrInvalid(Request $request): string|JsonResponse
    {
        $name = $this->playerNameRequestExtractor->extract($request);
        if (null === $name) {
            return $this->playerControllerWriteResponder->invalidPlayerName();
        }

        return $name;
    }
}
