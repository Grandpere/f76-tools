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
