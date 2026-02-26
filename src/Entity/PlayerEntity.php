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

use App\Repository\PlayerEntityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerEntityRepository::class)]
#[ORM\Table(name: 'player')]
#[ORM\UniqueConstraint(name: 'uniq_player_user_name', columns: ['user_id', 'name'])]
#[ORM\UniqueConstraint(name: 'uniq_player_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class PlayerEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private UserEntity $user;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(name: 'public_id', length: 26)]
    private string $publicId;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return isset($this->id) ? $this->id : null;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $normalized = trim($name);
        if ('' === $normalized) {
            throw new \InvalidArgumentException('Player name cannot be empty.');
        }
        $this->name = $normalized;

        return $this;
    }

    public function getPublicId(): string
    {
        if (!isset($this->publicId) || '' === $this->publicId) {
            throw new \LogicException('Player public ID is not initialized.');
        }

        return $this->publicId;
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
        if (!isset($this->publicId) || '' === $this->publicId) {
            $this->publicId = self::generatePublicId();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    private static function generatePublicId(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(16)), 0, 26));
    }
}
