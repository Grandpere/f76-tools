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
