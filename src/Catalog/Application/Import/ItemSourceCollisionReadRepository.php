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

namespace App\Catalog\Application\Import;

use App\Catalog\Domain\Item\ItemTypeEnum;

interface ItemSourceCollisionReadRepository
{
    /**
     * @return list<array{
     *     type:string,
     *     externalRef:string,
     *     itemCount:int,
     *     providerCount:int,
     *     providers:list<string>,
     *     sourceIds:list<int>
     * }>
     */
    public function findExternalRefCollisions(string $providerA, string $providerB, ?ItemTypeEnum $type, int $limit): array;
}
