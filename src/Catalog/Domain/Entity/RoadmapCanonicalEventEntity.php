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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'roadmap_canonical_event')]
#[ORM\Index(name: 'idx_roadmap_canonical_event_starts_at', columns: ['starts_at'])]
#[ORM\Index(name: 'idx_roadmap_canonical_event_ends_at', columns: ['ends_at'])]
#[ORM\HasLifecycleCallbacks]
class RoadmapCanonicalEventEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\Column(name: 'translation_key', length: 128, unique: true)]
    private string $translationKey;

    #[ORM\Column(name: 'starts_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startsAt;

    #[ORM\Column(name: 'ends_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $endsAt;

    #[ORM\Column(name: 'confidence_score')]
    private int $confidenceScore = 0;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt; // @phpstan-ignore property.onlyWritten

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt; // @phpstan-ignore property.onlyWritten

    /**
     * @var Collection<int, RoadmapCanonicalEventTranslationEntity>
     */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: RoadmapCanonicalEventTranslationEntity::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function setTranslationKey(string $translationKey): self
    {
        $this->translationKey = trim($translationKey);

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

    public function getConfidenceScore(): int
    {
        return $this->confidenceScore;
    }

    public function setConfidenceScore(int $confidenceScore): self
    {
        $this->confidenceScore = max(0, min(100, $confidenceScore));

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

    /**
     * @return Collection<int, RoadmapCanonicalEventTranslationEntity>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(RoadmapCanonicalEventTranslationEntity $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setEvent($this);
        }

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
