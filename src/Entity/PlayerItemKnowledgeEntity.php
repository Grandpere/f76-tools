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

use App\Progression\Infrastructure\Persistence\PlayerItemKnowledgeEntityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerItemKnowledgeEntityRepository::class)]
#[ORM\Table(name: 'player_item_knowledge')]
#[ORM\UniqueConstraint(name: 'uniq_player_item_knowledge', columns: ['player_id', 'item_id'])]
#[ORM\Index(name: 'idx_player_item_knowledge_player', columns: ['player_id'])]
#[ORM\Index(name: 'idx_player_item_knowledge_item', columns: ['item_id'])]
class PlayerItemKnowledgeEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\ManyToOne(targetEntity: PlayerEntity::class)]
    #[ORM\JoinColumn(name: 'player_id', nullable: false, onDelete: 'CASCADE')]
    private PlayerEntity $player;

    #[ORM\ManyToOne(targetEntity: ItemEntity::class)]
    #[ORM\JoinColumn(name: 'item_id', nullable: false, onDelete: 'CASCADE')]
    private ItemEntity $item;

    #[ORM\Column(name: 'learned_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $learnedAt;

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getPlayer(): PlayerEntity
    {
        return $this->player;
    }

    public function setPlayer(PlayerEntity $player): self
    {
        $this->player = $player;

        return $this;
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

    public function getLearnedAt(): DateTimeImmutable
    {
        return $this->learnedAt;
    }

    public function setLearnedAt(DateTimeImmutable $learnedAt): self
    {
        $this->learnedAt = $learnedAt;

        return $this;
    }
}
