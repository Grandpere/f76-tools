<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Infrastructure\Contact;

use App\Support\Infrastructure\Contact\ContactMessageEmailSender;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ContactMessageEmailSenderTest extends TestCase
{
    private MailerInterface&MockObject $mailer;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
    }

    public function testSendBuildsExpectedEmailPayload(): void
    {
        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (object $message): bool {
                if (!$message instanceof Email) {
                    return false;
                }

                return 'no-reply@f76.local' === $message->getFrom()[0]->getAddress()
                    && 'contact@example.com' === $message->getTo()[0]->getAddress()
                    && 'visitor@example.com' === $message->getReplyTo()[0]->getAddress()
                    && '[F76 Contact] Need help' === $message->getSubject()
                    && str_contains((string) $message->getTextBody(), "From: visitor@example.com\nIP: 127.0.0.1\n\nBody");
            }));

        $sender = new ContactMessageEmailSender($this->mailer, 'contact@example.com');
        $sender->send('visitor@example.com', 'Need help', 'Body', '127.0.0.1');
    }
}
