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

use App\Catalog\Application\Import\ItemImportPersistence;
use App\Catalog\Domain\Entity\ItemEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineItemImportPersistence implements ItemImportPersistence
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function persist(ItemEntity $item): void
    {
        $this->entityManager->persist($item);
    }

    public function mergeBookDuplicate(ItemEntity $duplicate, ItemEntity $keeper): void
    {
        $duplicateId = $duplicate->getId();
        $keeperId = $keeper->getId();
        if (null === $duplicateId || null === $keeperId || $duplicateId === $keeperId) {
            return;
        }

        $connection = $this->entityManager->getConnection();

        $connection->executeStatement(
            'DELETE FROM player_item_knowledge duplicate USING player_item_knowledge keeper
             WHERE duplicate.item_id = :duplicateId
               AND keeper.item_id = :keeperId
               AND keeper.player_id = duplicate.player_id',
            [
                'duplicateId' => $duplicateId,
                'keeperId' => $keeperId,
            ],
        );

        $connection->executeStatement(
            'UPDATE player_item_knowledge SET item_id = :keeperId WHERE item_id = :duplicateId',
            [
                'duplicateId' => $duplicateId,
                'keeperId' => $keeperId,
            ],
        );

        $this->entityManager->remove($duplicate);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
