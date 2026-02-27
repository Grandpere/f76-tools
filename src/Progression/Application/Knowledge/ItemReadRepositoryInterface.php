<?php

declare(strict_types=1);

namespace App\Progression\Application\Knowledge;

use App\Entity\ItemEntity;

interface ItemReadRepositoryInterface
{
    public function findOneByPublicId(string $publicId): ?ItemEntity;
}
