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

namespace App\Progression\Application\Knowledge;

use App\Entity\ItemEntity;

final class ItemReadApplicationService
{
    public function __construct(private readonly ItemReadRepositoryInterface $itemReadRepository)
    {
    }

    public function findByPublicId(string $itemPublicId): ?ItemEntity
    {
        return $this->itemReadRepository->findOneByPublicId($itemPublicId);
    }
}
