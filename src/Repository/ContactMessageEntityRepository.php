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

namespace App\Repository;

use App\Contract\ContactMessageWriterInterface;
use App\Entity\ContactMessageEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessageEntity>
 */
final class ContactMessageEntityRepository extends ServiceEntityRepository
    implements ContactMessageWriterInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessageEntity::class);
    }

    public function save(ContactMessageEntity $message): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($message);
        $entityManager->flush();
    }
}
