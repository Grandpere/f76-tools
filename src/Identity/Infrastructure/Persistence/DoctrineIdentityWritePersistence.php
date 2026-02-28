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

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Application\Common\IdentityWritePersistence;
use App\Identity\Domain\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineIdentityWritePersistence implements IdentityWritePersistence
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function persist(UserEntity $user): void
    {
        $this->entityManager->persist($user);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
