<?php

declare(strict_types=1);

namespace App\Support\UI\Admin;

use App\Support\Application\Contact\ContactMessageStatusUpdateResult;

final class ContactMessageStatusUpdateFeedbackMapper
{
    /**
     * @return array{flashType: 'success'|'warning', flashMessage: string}
     */
    public function map(ContactMessageStatusUpdateResult $result): array
    {
        return match ($result) {
            ContactMessageStatusUpdateResult::INVALID_STATUS => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_contact.flash.invalid_status',
            ],
            ContactMessageStatusUpdateResult::MESSAGE_NOT_FOUND => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_contact.flash.message_not_found',
            ],
            ContactMessageStatusUpdateResult::UPDATED => [
                'flashType' => 'success',
                'flashMessage' => 'admin_contact.flash.status_updated',
            ],
        };
    }
}
