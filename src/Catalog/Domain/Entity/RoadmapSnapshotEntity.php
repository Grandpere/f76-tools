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

use App\Catalog\Domain\Roadmap\RoadmapSnapshotStatusEnum;
use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'roadmap_snapshot')]
#[ORM\Index(name: 'idx_roadmap_snapshot_locale_status', columns: ['locale', 'status'])]
#[ORM\Index(name: 'idx_roadmap_snapshot_scanned_at', columns: ['scanned_at'])]
#[ORM\Index(name: 'idx_roadmap_snapshot_season_locale_status', columns: ['season_id', 'locale', 'status'])]
#[ORM\HasLifecycleCallbacks]
class RoadmapSnapshotEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\Column(length: 8)]
    private string $locale;

    #[ORM\Column(name: 'source_image_path', length: 1024)]
    private string $sourceImagePath;

    #[ORM\Column(name: 'source_image_hash', length: 64)]
    private string $sourceImageHash;

    #[ORM\Column(name: 'ocr_provider', length: 32)]
    private string $ocrProvider;

    #[ORM\Column(name: 'ocr_confidence', type: Types::FLOAT)]
    private float $ocrConfidence;

    #[ORM\Column(name: 'raw_text', type: Types::TEXT)]
    private string $rawText;

    #[ORM\Column(length: 16, enumType: RoadmapSnapshotStatusEnum::class)]
    private RoadmapSnapshotStatusEnum $status = RoadmapSnapshotStatusEnum::DRAFT;

    #[ORM\Column(name: 'scanned_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $scannedAt;

    #[ORM\Column(name: 'approved_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $approvedAt = null;

    #[ORM\ManyToOne(targetEntity: RoadmapSeasonEntity::class)]
    #[ORM\JoinColumn(name: 'season_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?RoadmapSeasonEntity $season = null;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: 'approved_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?UserEntity $approvedByUser = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, RoadmapEventEntity>
     */
    #[ORM\OneToMany(mappedBy: 'snapshot', targetEntity: RoadmapEventEntity::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $events;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->scannedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
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

    public function getSourceImagePath(): string
    {
        return $this->sourceImagePath;
    }

    public function setSourceImagePath(string $sourceImagePath): self
    {
        $this->sourceImagePath = trim($sourceImagePath);

        return $this;
    }

    public function getSourceImageHash(): string
    {
        return $this->sourceImageHash;
    }

    public function setSourceImageHash(string $sourceImageHash): self
    {
        $this->sourceImageHash = strtolower(trim($sourceImageHash));

        return $this;
    }

    public function getOcrProvider(): string
    {
        return $this->ocrProvider;
    }

    public function setOcrProvider(string $ocrProvider): self
    {
        $this->ocrProvider = trim($ocrProvider);

        return $this;
    }

    public function getOcrConfidence(): float
    {
        return $this->ocrConfidence;
    }

    public function setOcrConfidence(float $ocrConfidence): self
    {
        $this->ocrConfidence = max(0.0, min(1.0, $ocrConfidence));

        return $this;
    }

    public function getRawText(): string
    {
        return $this->rawText;
    }

    public function setRawText(string $rawText): self
    {
        $this->rawText = trim($rawText);

        return $this;
    }

    public function getStatus(): RoadmapSnapshotStatusEnum
    {
        return $this->status;
    }

    public function setStatus(RoadmapSnapshotStatusEnum $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getScannedAt(): DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function setScannedAt(DateTimeImmutable $scannedAt): self
    {
        $this->scannedAt = $scannedAt;

        return $this;
    }

    public function getApprovedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getSeason(): ?RoadmapSeasonEntity
    {
        return $this->season;
    }

    public function setSeason(?RoadmapSeasonEntity $season): self
    {
        $this->season = $season;

        return $this;
    }

    public function getApprovedByUser(): ?UserEntity
    {
        return $this->approvedByUser;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function approve(?UserEntity $user): self
    {
        $this->status = RoadmapSnapshotStatusEnum::APPROVED;
        $this->approvedAt = new DateTimeImmutable();
        $this->approvedByUser = $user;

        return $this;
    }

    /**
     * @return Collection<int, RoadmapEventEntity>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(RoadmapEventEntity $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setSnapshot($this);
        }

        return $this;
    }

    public function clearEvents(): self
    {
        foreach ($this->events as $event) {
            $this->events->removeElement($event);
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
