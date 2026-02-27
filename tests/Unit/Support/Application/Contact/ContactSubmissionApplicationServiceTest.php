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

namespace App\Tests\Unit\Support\Application\Contact;

use App\Contract\ContactMessageWriterInterface;
use App\Support\Application\Contact\ContactMessageApplicationService;
use App\Support\Application\Contact\ContactMessageEmailSenderInterface;
use App\Support\Application\Contact\ContactSubmissionApplicationService;
use App\Support\Application\Contact\ContactSubmissionInput;
use App\Support\Application\Contact\ContactSubmissionStatus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactSubmissionApplicationServiceTest extends TestCase
{
    public function testSubmitReturnsSentWhenPersistenceAndDeliverySucceed(): void
    {
        /** @var ContactMessageWriterInterface&MockObject $writer */
        $writer = $this->createMock(ContactMessageWriterInterface::class);
        $writer->expects(self::once())->method('save');
        $messageService = new ContactMessageApplicationService($writer);

        /** @var ContactMessageEmailSenderInterface&MockObject $emailSender */
        $emailSender = $this->createMock(ContactMessageEmailSenderInterface::class);
        $emailSender->expects(self::once())->method('send');

        $service = new ContactSubmissionApplicationService($messageService, $emailSender);

        $status = $service->submit(
            ContactSubmissionInput::create('visitor@example.com', 'Need help', 'Message long enough.'),
            '127.0.0.1',
        );

        self::assertSame(ContactSubmissionStatus::SENT, $status);
    }

    public function testSubmitReturnsPersistenceFailedWhenPersistenceThrows(): void
    {
        /** @var ContactMessageWriterInterface&MockObject $writer */
        $writer = $this->createMock(ContactMessageWriterInterface::class);
        $writer->method('save')->willThrowException(new RuntimeException('db'));
        $messageService = new ContactMessageApplicationService($writer);

        /** @var ContactMessageEmailSenderInterface&MockObject $emailSender */
        $emailSender = $this->createMock(ContactMessageEmailSenderInterface::class);
        $emailSender->expects(self::never())->method('send');

        $service = new ContactSubmissionApplicationService($messageService, $emailSender);

        $status = $service->submit(
            ContactSubmissionInput::create('visitor@example.com', 'Need help', 'Message long enough.'),
            '127.0.0.1',
        );

        self::assertSame(ContactSubmissionStatus::PERSISTENCE_FAILED, $status);
    }

    public function testSubmitReturnsSentWithDeliveryFailureWhenDeliveryThrows(): void
    {
        /** @var ContactMessageWriterInterface&MockObject $writer */
        $writer = $this->createMock(ContactMessageWriterInterface::class);
        $writer->expects(self::once())->method('save');
        $messageService = new ContactMessageApplicationService($writer);

        /** @var ContactMessageEmailSenderInterface&MockObject $emailSender */
        $emailSender = $this->createMock(ContactMessageEmailSenderInterface::class);
        $emailSender->method('send')->willThrowException(new RuntimeException('smtp'));

        $service = new ContactSubmissionApplicationService($messageService, $emailSender);

        $status = $service->submit(
            ContactSubmissionInput::create('visitor@example.com', 'Need help', 'Message long enough.'),
            '127.0.0.1',
        );

        self::assertSame(ContactSubmissionStatus::SENT_WITH_DELIVERY_FAILURE, $status);
    }
}
