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

use App\Domain\Support\Contact\ContactMessageStatusEnum;
use App\Entity\ContactMessageEntity;
use App\Support\Application\Contact\ContactMessageApplicationService;
use App\Support\Application\Contact\ContactMessageWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ContactMessageApplicationServiceTest extends TestCase
{
    public function testCreateMessageBuildsAndPersistsContactMessage(): void
    {
        /** @var ContactMessageWriter&MockObject $writer */
        $writer = $this->createMock(ContactMessageWriter::class);
        $service = new ContactMessageApplicationService($writer);

        $writer
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (mixed $value): bool {
                if (!$value instanceof ContactMessageEntity) {
                    return false;
                }

                return 'visitor@example.com' === $value->getEmail()
                    && 'Need help' === $value->getSubject()
                    && 'Hello, I need help with this feature.' === $value->getMessage()
                    && '127.0.0.1' === $value->getIp()
                    && ContactMessageStatusEnum::NEW === $value->getStatus();
            }));

        $entity = $service->createMessage(
            'visitor@example.com',
            'Need help',
            'Hello, I need help with this feature.',
            '127.0.0.1',
        );

        self::assertSame('visitor@example.com', $entity->getEmail());
        self::assertSame('Need help', $entity->getSubject());
        self::assertSame('Hello, I need help with this feature.', $entity->getMessage());
        self::assertSame('127.0.0.1', $entity->getIp());
        self::assertSame(ContactMessageStatusEnum::NEW, $entity->getStatus());
    }
}
