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

namespace App\Entity;

use App\Identity\Infrastructure\Persistence\UserEntityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserEntityRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class UserEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\Column(length: 180)]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'is_email_verified', options: ['default' => true])]
    private bool $isEmailVerified = true;

    #[ORM\Column(name: 'email_verification_token_hash', length: 64, nullable: true)]
    private ?string $emailVerificationTokenHash = null;

    #[ORM\Column(name: 'email_verification_expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $emailVerificationExpiresAt = null;

    #[ORM\Column(name: 'email_verification_requested_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $emailVerificationRequestedAt = null;

    #[ORM\Column(name: 'reset_password_token_hash', length: 64, nullable: true)]
    private ?string $resetPasswordTokenHash = null;

    #[ORM\Column(name: 'reset_password_expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $resetPasswordExpiresAt = null;

    #[ORM\Column(name: 'reset_password_requested_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $resetPasswordRequestedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $normalized = mb_strtolower(trim($email));
        if ('' === $normalized) {
            throw new InvalidArgumentException('Email cannot be empty.');
        }

        $this->email = $normalized;

        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new LogicException('User email must not be empty.');
        }

        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        /** @var list<string> $uniqueRoles */
        $uniqueRoles = array_values(array_unique($roles));

        return $uniqueRoles;
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $normalized = [];
        foreach ($roles as $role) {
            $role = strtoupper(trim($role));
            if ('' === $role) {
                continue;
            }
            $normalized[] = $role;
        }

        /** @var list<string> $uniqueRoles */
        $uniqueRoles = array_values(array_unique($normalized));
        $this->roles = $uniqueRoles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): self
    {
        $this->isEmailVerified = $isEmailVerified;

        return $this;
    }

    public function getEmailVerificationTokenHash(): ?string
    {
        return $this->emailVerificationTokenHash;
    }

    public function setEmailVerificationTokenHash(?string $emailVerificationTokenHash): self
    {
        $this->emailVerificationTokenHash = $emailVerificationTokenHash;

        return $this;
    }

    public function getEmailVerificationExpiresAt(): ?DateTimeImmutable
    {
        return $this->emailVerificationExpiresAt;
    }

    public function setEmailVerificationExpiresAt(?DateTimeImmutable $emailVerificationExpiresAt): self
    {
        $this->emailVerificationExpiresAt = $emailVerificationExpiresAt;

        return $this;
    }

    public function getEmailVerificationRequestedAt(): ?DateTimeImmutable
    {
        return $this->emailVerificationRequestedAt;
    }

    public function setEmailVerificationRequestedAt(?DateTimeImmutable $emailVerificationRequestedAt): self
    {
        $this->emailVerificationRequestedAt = $emailVerificationRequestedAt;

        return $this;
    }

    public function getResetPasswordTokenHash(): ?string
    {
        return $this->resetPasswordTokenHash;
    }

    public function setResetPasswordTokenHash(?string $resetPasswordTokenHash): self
    {
        $this->resetPasswordTokenHash = $resetPasswordTokenHash;

        return $this;
    }

    public function getResetPasswordExpiresAt(): ?DateTimeImmutable
    {
        return $this->resetPasswordExpiresAt;
    }

    public function setResetPasswordExpiresAt(?DateTimeImmutable $resetPasswordExpiresAt): self
    {
        $this->resetPasswordExpiresAt = $resetPasswordExpiresAt;

        return $this;
    }

    public function getResetPasswordRequestedAt(): ?DateTimeImmutable
    {
        return $this->resetPasswordRequestedAt;
    }

    public function setResetPasswordRequestedAt(?DateTimeImmutable $resetPasswordRequestedAt): self
    {
        $this->resetPasswordRequestedAt = $resetPasswordRequestedAt;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
