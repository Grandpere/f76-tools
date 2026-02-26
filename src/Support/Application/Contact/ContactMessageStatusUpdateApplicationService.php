<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

use App\Domain\Support\Contact\ContactMessageStatusEnum;
use App\Entity\ContactMessageEntity;

final class ContactMessageStatusUpdateApplicationService
{
    public function __construct(
        private readonly ContactMessageStatusWriteRepositoryInterface $contactMessageRepository,
    ) {
    }

    public function update(int $id, mixed $rawStatus): ContactMessageStatusUpdateResult
    {
        $status = $this->sanitizeStatus($rawStatus);
        if (!$status instanceof ContactMessageStatusEnum) {
            return ContactMessageStatusUpdateResult::INVALID_STATUS;
        }

        $message = $this->contactMessageRepository->getById($id);
        if (!$message instanceof ContactMessageEntity) {
            return ContactMessageStatusUpdateResult::MESSAGE_NOT_FOUND;
        }

        $message->setStatus($status);
        $this->contactMessageRepository->save($message);

        return ContactMessageStatusUpdateResult::UPDATED;
    }

    private function sanitizeStatus(mixed $value): ?ContactMessageStatusEnum
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ('' === $normalized) {
            return null;
        }

        return ContactMessageStatusEnum::tryFrom($normalized);
    }
}
