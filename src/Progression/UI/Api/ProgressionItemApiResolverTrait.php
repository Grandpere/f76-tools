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
use Symfony\Component\HttpFoundation\JsonResponse;

trait ProgressionItemApiResolverTrait
{
    abstract protected function progressionItemApiResolver(): ProgressionItemApiResolver;

    protected function resolveItemOrNotFound(string $itemId): ItemEntity|JsonResponse
    {
        return $this->progressionItemApiResolver()->resolveOrNotFound($itemId);
    }
}
