<?php

declare(strict_types=1);

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
