<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\UI\Admin;

use App\Support\Application\Contact\ContactMessageStatusUpdateResult;
use App\Support\UI\Admin\ContactMessageStatusUpdateFeedbackMapper;
use PHPUnit\Framework\TestCase;

final class ContactMessageStatusUpdateFeedbackMapperTest extends TestCase
{
    public function testMapReturnsExpectedFeedback(): void
    {
        $mapper = new ContactMessageStatusUpdateFeedbackMapper();

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_contact.flash.invalid_status',
        ], $mapper->map(ContactMessageStatusUpdateResult::INVALID_STATUS));

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_contact.flash.message_not_found',
        ], $mapper->map(ContactMessageStatusUpdateResult::MESSAGE_NOT_FOUND));

        self::assertSame([
            'flashType' => 'success',
            'flashMessage' => 'admin_contact.flash.status_updated',
        ], $mapper->map(ContactMessageStatusUpdateResult::UPDATED));
    }
}
