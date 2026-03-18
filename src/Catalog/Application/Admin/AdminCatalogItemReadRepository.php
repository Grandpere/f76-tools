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

namespace App\Catalog\Application\Admin;

use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;

interface AdminCatalogItemReadRepository
{
    public function countByAdminQuery(?ItemTypeEnum $type, ?string $query): int;

    /**
     * @return list<ItemEntity>
     */
    public function findByAdminQuery(?ItemTypeEnum $type, ?string $query, int $page, int $perPage): array;

    public function findOneDetailedByPublicId(string $publicId): ?ItemEntity;
}
