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

use App\Repository\MinervaRotationEntityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MinervaRotationEntityRepository::class)]
#[ORM\Table(name: 'minerva_rotation')]
#[ORM\Index(name: 'idx_minerva_rotation_starts_at', columns: ['starts_at'])]
#[ORM\Index(name: 'idx_minerva_rotation_ends_at', columns: ['ends_at'])]
#[ORM\Index(name: 'idx_minerva_rotation_list_cycle', columns: ['list_cycle'])]
#[ORM\HasLifecycleCallbacks]
class MinervaRotationEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\Column(length: 120)]
    private string $location;

    #[ORM\Column(name: 'list_cycle')]
    private int $listCycle;

    #[ORM\Column(name: 'starts_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startsAt;

    #[ORM\Column(name: 'ends_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $endsAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return isset($this->id) ? $this->id : null;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $normalized = trim($location);
        if ('' === $normalized) {
            throw new \InvalidArgumentException('Location cannot be empty.');
        }
        $this->location = $normalized;

        return $this;
    }

    public function getListCycle(): int
    {
        return $this->listCycle;
    }

    public function setListCycle(int $listCycle): self
    {
        if ($listCycle < 1) {
            throw new \InvalidArgumentException('List cycle must be greater than zero.');
        }
        $this->listCycle = $listCycle;

        return $this;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

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
