<?php

declare(strict_types=1);

namespace App\Catalog\Application\Import;

use App\Entity\ItemEntity;

interface ItemImportPersistenceInterface
{
    public function persist(ItemEntity $item): void;

    public function flush(): void;
}
