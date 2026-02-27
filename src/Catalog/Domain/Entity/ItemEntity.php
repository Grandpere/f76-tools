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

use App\Catalog\Domain\Item\ItemInterface;
use App\Catalog\Domain\Item\ItemTypeEnum;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'item')]
#[ORM\UniqueConstraint(name: 'uniq_item_type_source_id', columns: ['type', 'source_id'])]
#[ORM\UniqueConstraint(name: 'uniq_item_name_key', columns: ['name_key'])]
#[ORM\UniqueConstraint(name: 'uniq_item_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_item_type_rank', columns: ['type', 'rank'])]
#[ORM\HasLifecycleCallbacks]
class ItemEntity implements ItemInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\Column(name: 'source_id')]
    private int $sourceId;

    #[ORM\Column(name: 'public_id', length: 26)]
    private string $publicId;

    #[ORM\Column(length: 16, enumType: ItemTypeEnum::class)]
    private ItemTypeEnum $type;

    #[ORM\Column(name: 'name_key', length: 255)]
    private string $nameKey;

    #[ORM\Column(name: 'desc_key', length: 255, nullable: true)]
    private ?string $descKey = null;

    #[ORM\Column(name: 'form_id', length: 32, nullable: true)]
    private ?string $formId = null;

    #[ORM\Column(name: 'editor_id', length: 255, nullable: true)]
    private ?string $editorId = null;

    #[ORM\Column(nullable: true)]
    private ?int $rank = null;

    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    #[ORM\Column(name: 'price_minerva', nullable: true)]
    private ?int $priceMinerva = null;

    #[ORM\Column(name: 'is_new', options: ['default' => false])]
    private bool $isNew = false;

    #[ORM\Column(name: 'drop_raid', options: ['default' => false])]
    private bool $dropRaid = false;

    #[ORM\Column(name: 'drop_burningsprings', options: ['default' => false])]
    private bool $dropBurningSprings = false;

    #[ORM\Column(name: 'drop_dailyops', options: ['default' => false])]
    private bool $dropDailyOps = false;

    #[ORM\Column(name: 'vendor_regs', options: ['default' => false])]
    private bool $vendorRegs = false;

    #[ORM\Column(name: 'vendor_samuel', options: ['default' => false])]
    private bool $vendorSamuel = false;

    #[ORM\Column(name: 'vendor_mortimer', options: ['default' => false])]
    private bool $vendorMortimer = false;

    #[ORM\Column(name: 'info_html', type: Types::TEXT, nullable: true)]
    private ?string $infoHtml = null;

    #[ORM\Column(name: 'drop_sources_html', type: Types::TEXT, nullable: true)]
    private ?string $dropSourcesHtml = null;

    #[ORM\Column(name: 'relations_html', type: Types::TEXT, nullable: true)]
    private ?string $relationsHtml = null;

    #[ORM\Column(name: 'wiki_url', length: 1024, nullable: true)]
    private ?string $wikiUrl = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $tradeable = false;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, ItemBookListEntity>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: ItemBookListEntity::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $bookLists;

    public function __construct()
    {
        $this->bookLists = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getSourceId(): int
    {
        return $this->sourceId;
    }

    public function setSourceId(int $sourceId): self
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    public function getPublicId(): string
    {
        if (!isset($this->publicId) || '' === $this->publicId) {
            throw new LogicException('Item public ID is not initialized.');
        }

        return $this->publicId;
    }

    public function getType(): ItemTypeEnum
    {
        return $this->type;
    }

    public function setType(ItemTypeEnum $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getNameKey(): string
    {
        return $this->nameKey;
    }

    public function setNameKey(string $nameKey): self
    {
        $this->nameKey = $nameKey;

        return $this;
    }

    public function getDescKey(): ?string
    {
        return $this->descKey;
    }

    public function setDescKey(?string $descKey): self
    {
        $this->descKey = $descKey;

        return $this;
    }

    public function getFormId(): ?string
    {
        return $this->formId;
    }

    public function setFormId(?string $formId): self
    {
        $this->formId = $formId;

        return $this;
    }

    public function getEditorId(): ?string
    {
        return $this->editorId;
    }

    public function setEditorId(?string $editorId): self
    {
        $this->editorId = $editorId;

        return $this;
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(?int $rank): self
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * @return Collection<int, ItemBookListEntity>
     */
    public function getBookLists(): Collection
    {
        return $this->bookLists;
    }

    public function addBookList(int $listNumber, bool $isSpecialList): self
    {
        foreach ($this->bookLists as $bookList) {
            if ($bookList->getListNumber() === $listNumber) {
                if ($isSpecialList && !$bookList->isSpecialList()) {
                    $bookList->setIsSpecialList(true);
                }

                return $this;
            }
        }

        $bookList = new ItemBookListEntity()
            ->setItem($this)
            ->setListNumber($listNumber)
            ->setIsSpecialList($isSpecialList);
        $this->bookLists->add($bookList);

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getPriceMinerva(): ?int
    {
        return $this->priceMinerva;
    }

    public function setPriceMinerva(?int $priceMinerva): self
    {
        $this->priceMinerva = $priceMinerva;

        return $this;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function setIsNew(bool $isNew): self
    {
        $this->isNew = $isNew;

        return $this;
    }

    public function isDropRaid(): bool
    {
        return $this->dropRaid;
    }

    public function setDropRaid(bool $dropRaid): self
    {
        $this->dropRaid = $dropRaid;

        return $this;
    }

    public function isDropBurningSprings(): bool
    {
        return $this->dropBurningSprings;
    }

    public function setDropBurningSprings(bool $dropBurningSprings): self
    {
        $this->dropBurningSprings = $dropBurningSprings;

        return $this;
    }

    public function isDropDailyOps(): bool
    {
        return $this->dropDailyOps;
    }

    public function setDropDailyOps(bool $dropDailyOps): self
    {
        $this->dropDailyOps = $dropDailyOps;

        return $this;
    }

    public function isVendorRegs(): bool
    {
        return $this->vendorRegs;
    }

    public function setVendorRegs(bool $vendorRegs): self
    {
        $this->vendorRegs = $vendorRegs;

        return $this;
    }

    public function isVendorSamuel(): bool
    {
        return $this->vendorSamuel;
    }

    public function setVendorSamuel(bool $vendorSamuel): self
    {
        $this->vendorSamuel = $vendorSamuel;

        return $this;
    }

    public function isVendorMortimer(): bool
    {
        return $this->vendorMortimer;
    }

    public function setVendorMortimer(bool $vendorMortimer): self
    {
        $this->vendorMortimer = $vendorMortimer;

        return $this;
    }

    public function getInfoHtml(): ?string
    {
        return $this->infoHtml;
    }

    public function setInfoHtml(?string $infoHtml): self
    {
        $this->infoHtml = $infoHtml;

        return $this;
    }

    public function getDropSourcesHtml(): ?string
    {
        return $this->dropSourcesHtml;
    }

    public function setDropSourcesHtml(?string $dropSourcesHtml): self
    {
        $this->dropSourcesHtml = $dropSourcesHtml;

        return $this;
    }

    public function getRelationsHtml(): ?string
    {
        return $this->relationsHtml;
    }

    public function setRelationsHtml(?string $relationsHtml): self
    {
        $this->relationsHtml = $relationsHtml;

        return $this;
    }

    public function getWikiUrl(): ?string
    {
        return $this->wikiUrl;
    }

    public function setWikiUrl(?string $wikiUrl): self
    {
        $this->wikiUrl = $wikiUrl;

        return $this;
    }

    public function isTradeable(): bool
    {
        return $this->tradeable;
    }

    public function setTradeable(bool $tradeable): self
    {
        $this->tradeable = $tradeable;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        if (!isset($this->publicId) || '' === $this->publicId) {
            $this->publicId = (string) new Ulid();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[Assert\Callback]
    public function validateRankConsistency(ExecutionContextInterface $context): void
    {
        if (ItemTypeEnum::MISC === $this->type && null === $this->rank) {
            $context->buildViolation('Le rank est obligatoire pour les items MISC.')
                ->atPath('rank')
                ->addViolation();
        }

        if (ItemTypeEnum::BOOK === $this->type && null !== $this->rank) {
            $context->buildViolation('Le rank doit etre null pour les items BOOK.')
                ->atPath('rank')
                ->addViolation();
        }
    }
}
