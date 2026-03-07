<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'roadmap_event')]
#[ORM\Index(name: 'idx_roadmap_event_snapshot', columns: ['snapshot_id'])]
#[ORM\Index(name: 'idx_roadmap_event_locale_starts_at', columns: ['locale', 'starts_at'])]
#[ORM\Index(name: 'idx_roadmap_event_locale_ends_at', columns: ['locale', 'ends_at'])]
#[ORM\HasLifecycleCallbacks]
class RoadmapEventEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\ManyToOne(targetEntity: RoadmapSnapshotEntity::class, inversedBy: 'events')]
    #[ORM\JoinColumn(name: 'snapshot_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private RoadmapSnapshotEntity $snapshot;

    #[ORM\Column(length: 8)]
    private string $locale;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(name: 'starts_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startsAt;

    #[ORM\Column(name: 'ends_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $endsAt;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getSnapshot(): RoadmapSnapshotEntity
    {
        return $this->snapshot;
    }

    public function setSnapshot(RoadmapSnapshotEntity $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = strtolower(trim($locale));

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = max(0, $sortOrder);

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
