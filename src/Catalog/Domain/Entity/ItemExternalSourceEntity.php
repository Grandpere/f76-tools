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
#[ORM\Table(name: 'item_external_source')]
#[ORM\UniqueConstraint(name: 'uniq_item_external_source_item_provider_ref', columns: ['item_id', 'provider', 'external_ref'])]
#[ORM\Index(name: 'idx_item_external_source_provider_ref', columns: ['provider', 'external_ref'])]
#[ORM\Index(name: 'idx_item_external_source_item_provider', columns: ['item_id', 'provider'])]
#[ORM\HasLifecycleCallbacks]
class ItemExternalSourceEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\ManyToOne(targetEntity: ItemEntity::class, inversedBy: 'externalSources')]
    #[ORM\JoinColumn(name: 'item_id', nullable: false, onDelete: 'CASCADE')]
    private ItemEntity $item;

    #[ORM\Column(length: 64)]
    private string $provider;

    #[ORM\Column(name: 'external_ref', length: 255)]
    private string $externalRef;

    #[ORM\Column(name: 'external_url', length: 1024, nullable: true)]
    private ?string $externalUrl = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getItem(): ItemEntity
    {
        return $this->item;
    }

    public function setItem(ItemEntity $item): self
    {
        $this->item = $item;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = strtolower(trim($provider));

        return $this;
    }

    public function getExternalRef(): string
    {
        return $this->externalRef;
    }

    public function setExternalRef(string $externalRef): self
    {
        $this->externalRef = trim($externalRef);

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = null !== $externalUrl ? trim($externalUrl) : null;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

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
