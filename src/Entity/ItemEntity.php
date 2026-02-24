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

use App\Domain\Item\ItemInterface;
use App\Domain\Item\ItemTypeEnum;
use App\Repository\ItemEntityRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ItemEntityRepository::class)]
#[ORM\Table(name: 'item')]
#[ORM\UniqueConstraint(name: 'uniq_item_type_source_id', columns: ['type', 'source_id'])]
#[ORM\UniqueConstraint(name: 'uniq_item_name_key', columns: ['name_key'])]
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
        return isset($this->id) ? $this->id : null;
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
