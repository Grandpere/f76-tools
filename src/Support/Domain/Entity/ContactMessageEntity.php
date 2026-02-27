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

use App\Support\Domain\Contact\ContactMessageStatusEnum;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

#[ORM\Entity]
#[ORM\Table(name: 'contact_message')]
#[ORM\Index(name: 'idx_contact_message_status', columns: ['status'])]
#[ORM\Index(name: 'idx_contact_message_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class ContactMessageEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id; // @phpstan-ignore property.onlyRead

    #[ORM\Column(length: 320)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 32, enumType: ContactMessageStatusEnum::class)]
    private ContactMessageStatusEnum $status = ContactMessageStatusEnum::NEW;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $normalized = mb_strtolower(trim($email));
        if ('' === $normalized) {
            throw new InvalidArgumentException('Contact email cannot be empty.');
        }
        $this->email = $normalized;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $normalized = trim($subject);
        if ('' === $normalized) {
            throw new InvalidArgumentException('Contact subject cannot be empty.');
        }
        $this->subject = $normalized;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $normalized = trim($message);
        if ('' === $normalized) {
            throw new InvalidArgumentException('Contact message cannot be empty.');
        }
        $this->message = $normalized;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $normalized = is_string($ip) ? trim($ip) : null;
        $this->ip = '' === $normalized ? null : $normalized;

        return $this;
    }

    public function getStatus(): ContactMessageStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ContactMessageStatusEnum $status): self
    {
        $this->status = $status;

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
        if (!isset($this->status)) {
            $this->status = ContactMessageStatusEnum::NEW;
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
