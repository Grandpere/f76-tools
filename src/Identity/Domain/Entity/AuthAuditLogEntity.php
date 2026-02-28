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

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'auth_audit_log')]
#[ORM\Index(name: 'idx_auth_audit_log_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_auth_audit_log_occurred_at', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_auth_audit_log_event', columns: ['event'])]
#[ORM\HasLifecycleCallbacks]
class AuthAuditLogEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?UserEntity $user = null;

    #[ORM\Column(name: 'email_hash', length: 64, nullable: true)]
    private ?string $emailHash = null;

    #[ORM\Column(length: 16)]
    private string $level;

    #[ORM\Column(length: 128)]
    private string $event;

    #[ORM\Column(name: 'client_ip', length: 45, nullable: true)]
    private ?string $clientIp = null;

    /**
     * @var array<string, bool|float|int|string|null>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $occurredAt;

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getEmailHash(): ?string
    {
        return $this->emailHash;
    }

    public function setEmailHash(?string $emailHash): self
    {
        $normalized = is_string($emailHash) ? trim($emailHash) : '';
        $this->emailHash = '' !== $normalized ? $normalized : null;

        return $this;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): self
    {
        $this->level = trim($level);

        return $this;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function setEvent(string $event): self
    {
        $this->event = trim($event);

        return $this;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function setClientIp(?string $clientIp): self
    {
        $normalized = is_string($clientIp) ? trim($clientIp) : '';
        $this->clientIp = '' !== $normalized ? $normalized : null;

        return $this;
    }

    /**
     * @return array<string, bool|float|int|string|null>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string, bool|float|int|string|null>|null $context
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
