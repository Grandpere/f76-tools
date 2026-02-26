<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Entity\UserEntity;
use App\Identity\Application\Common\IdentityPasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SymfonyIdentityPasswordHasher implements IdentityPasswordHasherInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function hash(UserEntity $user, string $plainPassword): string
    {
        return $this->passwordHasher->hashPassword($user, $plainPassword);
    }
}
