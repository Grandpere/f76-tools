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

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'item_book_list')]
#[ORM\UniqueConstraint(name: 'uniq_item_book_list', columns: ['item_id', 'list_number'])]
#[ORM\Index(name: 'idx_item_book_list_special', columns: ['is_special_list'])]
class ItemBookListEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\ManyToOne(targetEntity: ItemEntity::class, inversedBy: 'bookLists')]
    #[ORM\JoinColumn(name: 'item_id', nullable: false, onDelete: 'CASCADE')]
    private ItemEntity $item;

    #[ORM\Column(name: 'list_number')]
    private int $listNumber;

    #[ORM\Column(name: 'is_special_list', options: ['default' => false])]
    private bool $isSpecialList = false;

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

    public function getListNumber(): int
    {
        return $this->listNumber;
    }

    public function setListNumber(int $listNumber): self
    {
        $this->listNumber = $listNumber;

        return $this;
    }

    public function isSpecialList(): bool
    {
        return $this->isSpecialList;
    }

    public function setIsSpecialList(bool $isSpecialList): self
    {
        $this->isSpecialList = $isSpecialList;

        return $this;
    }
}
