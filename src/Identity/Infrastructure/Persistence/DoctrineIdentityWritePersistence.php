<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Application\VerifyEmail\IdentityWritePersistenceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineIdentityWritePersistence implements IdentityWritePersistenceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
