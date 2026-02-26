<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Application\Contact;

use App\Domain\Support\Contact\ContactMessageStatusEnum;
use App\Entity\ContactMessageEntity;
use App\Support\Application\Contact\ContactMessageStatusUpdateApplicationService;
use App\Support\Application\Contact\ContactMessageStatusUpdateResult;
use App\Support\Application\Contact\ContactMessageStatusWriteRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ContactMessageStatusUpdateApplicationServiceTest extends TestCase
{
    public function testUpdateReturnsInvalidStatusWhenStatusIsInvalid(): void
    {
        /** @var ContactMessageStatusWriteRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(ContactMessageStatusWriteRepositoryInterface::class);
        $repository->expects(self::never())->method('getById');
        $repository->expects(self::never())->method('save');

        $service = new ContactMessageStatusUpdateApplicationService($repository);

        $result = $service->update(10, 'unknown-status');

        self::assertSame(ContactMessageStatusUpdateResult::INVALID_STATUS, $result);
    }

    public function testUpdateReturnsMessageNotFoundWhenEntityDoesNotExist(): void
    {
        /** @var ContactMessageStatusWriteRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(ContactMessageStatusWriteRepositoryInterface::class);
        $repository->expects(self::once())->method('getById')->with(10)->willReturn(null);
        $repository->expects(self::never())->method('save');

        $service = new ContactMessageStatusUpdateApplicationService($repository);

        $result = $service->update(10, ContactMessageStatusEnum::IN_PROGRESS->value);

        self::assertSame(ContactMessageStatusUpdateResult::MESSAGE_NOT_FOUND, $result);
    }

    public function testUpdateReturnsUpdatedWhenStatusCanBeChanged(): void
    {
        $entity = (new ContactMessageEntity())
            ->setEmail('visitor@example.com')
            ->setSubject('Need help')
            ->setMessage('Long enough message for test.')
            ->setStatus(ContactMessageStatusEnum::NEW);

        /** @var ContactMessageStatusWriteRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(ContactMessageStatusWriteRepositoryInterface::class);
        $repository->expects(self::once())->method('getById')->with(10)->willReturn($entity);
        $repository->expects(self::once())->method('save')->with($entity);

        $service = new ContactMessageStatusUpdateApplicationService($repository);

        $result = $service->update(10, ContactMessageStatusEnum::IN_PROGRESS->value);

        self::assertSame(ContactMessageStatusUpdateResult::UPDATED, $result);
        self::assertSame(ContactMessageStatusEnum::IN_PROGRESS, $entity->getStatus());
    }
}
