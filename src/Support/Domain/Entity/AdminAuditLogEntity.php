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

namespace App\Support\Domain\Entity;

use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_audit_log')]
#[ORM\Index(name: 'idx_admin_audit_log_actor_user', columns: ['actor_user_id'])]
#[ORM\Index(name: 'idx_admin_audit_log_target_user', columns: ['target_user_id'])]
#[ORM\Index(name: 'idx_admin_audit_log_action', columns: ['action'])]
#[ORM\Index(name: 'idx_admin_audit_log_occurred_at', columns: ['occurred_at'])]
#[ORM\HasLifecycleCallbacks]
class AdminAuditLogEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: 'actor_user_id', nullable: false, onDelete: 'CASCADE')]
    private UserEntity $actorUser;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: 'target_user_id', nullable: true, onDelete: 'SET NULL')]
    private ?UserEntity $targetUser = null;

    #[ORM\Column(length: 64)]
    private string $action;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $occurredAt;

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getActorUser(): UserEntity
    {
        return $this->actorUser;
    }

    public function setActorUser(UserEntity $actorUser): self
    {
        $this->actorUser = $actorUser;

        return $this;
    }

    public function getTargetUser(): ?UserEntity
    {
        return $this->targetUser;
    }

    public function setTargetUser(?UserEntity $targetUser): self
    {
        $this->targetUser = $targetUser;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = trim($action);

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function setContext(?array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (!isset($this->occurredAt)) {
            $this->occurredAt = new DateTimeImmutable();
        }
    }
}
