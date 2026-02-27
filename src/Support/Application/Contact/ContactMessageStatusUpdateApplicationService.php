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

use App\Entity\ContactMessageEntity;
use App\Support\Domain\Contact\ContactMessageStatusEnum;

final class ContactMessageStatusUpdateApplicationService
{
    public function __construct(
        private readonly ContactMessageStatusWriteRepositoryInterface $contactMessageRepository,
    ) {
    }

    public function update(int $id, ContactMessageStatusUpdateRequest $request): ContactMessageStatusUpdateResult
    {
        $status = $request->status;
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
}
