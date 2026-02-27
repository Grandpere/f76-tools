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
use App\Progression\Application\Knowledge\ItemReadApplicationService;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ProgressionItemApiResolver
{
    public function __construct(
        private readonly ItemReadApplicationService $itemReadApplicationService,
        private readonly ProgressionApiErrorResponder $progressionApiErrorResponder,
    ) {
    }

    public function resolveOrNotFound(string $itemId): ItemEntity|JsonResponse
    {
        $item = $this->itemReadApplicationService->findByPublicId($itemId);
        if (null === $item) {
            return $this->progressionApiErrorResponder->itemNotFound();
        }

        return $item;
    }
}
