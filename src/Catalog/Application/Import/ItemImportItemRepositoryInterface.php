<?php

declare(strict_types=1);

namespace App\Catalog\Application\Import;

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;

interface ItemImportItemRepositoryInterface
{
    public function findOneByTypeAndSourceId(ItemTypeEnum $type, int $sourceId): ?ItemEntity;
}
