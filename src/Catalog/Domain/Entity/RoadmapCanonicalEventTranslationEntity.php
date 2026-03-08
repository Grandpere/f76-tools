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

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'roadmap_canonical_event_translation')]
#[ORM\UniqueConstraint(name: 'uniq_roadmap_canonical_event_locale', columns: ['event_id', 'locale'])]
#[ORM\HasLifecycleCallbacks]
class RoadmapCanonicalEventTranslationEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\ManyToOne(targetEntity: RoadmapCanonicalEventEntity::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private RoadmapCanonicalEventEntity $event;

    #[ORM\Column(length: 8)]
    private string $locale;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt; // @phpstan-ignore property.onlyWritten

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt; // @phpstan-ignore property.onlyWritten

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getEvent(): RoadmapCanonicalEventEntity
    {
        return $this->event;
    }

    public function setEvent(RoadmapCanonicalEventEntity $event): self
    {
        $this->event = $event;

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
