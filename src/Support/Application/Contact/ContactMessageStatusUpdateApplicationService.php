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
