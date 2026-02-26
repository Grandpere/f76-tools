<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Import;

use App\Catalog\Application\Import\ItemImportPersistenceInterface;
use App\Entity\ItemEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineItemImportPersistence implements ItemImportPersistenceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function persist(ItemEntity $item): void
    {
        $this->entityManager->persist($item);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
