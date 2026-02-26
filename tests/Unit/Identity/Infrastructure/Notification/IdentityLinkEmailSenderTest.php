<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Notification;

use App\Identity\Application\Notification\IdentitySignedLinkGeneratorInterface;
use App\Identity\Infrastructure\Notification\IdentityLinkEmailSender;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class IdentityLinkEmailSenderTest extends TestCase
{
    private IdentitySignedLinkGeneratorInterface&MockObject $signedUrlGenerator;
    private TranslatorInterface&MockObject $translator;
    private MailerInterface&MockObject $mailer;

    protected function setUp(): void
    {
        $this->signedUrlGenerator = $this->createMock(IdentitySignedLinkGeneratorInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
    }

    public function testSendVerificationLinkBuildsSignedUrlAndSendsMail(): void
    {
        $this->signedUrlGenerator
            ->expects(self::once())
            ->method('generateVerificationUrl')
            ->with('fr', 'abc')
            ->willReturn('https://example.test/verify');

        $this->translator->method('trans')->willReturnMap([
            ['security.verify.email_subject', [], null, null, 'Subject'],
            ['security.verify.email_intro', [], null, null, 'Intro'],
        ]);

        $this->mailer->expects(self::once())->method('send');

        $sender = new IdentityLinkEmailSender($this->signedUrlGenerator, $this->translator, $this->mailer);
        $sender->sendVerificationLink('user@example.com', 'fr', 'abc');
    }

    public function testSendResetLinkDoesNotThrowWhenMailerFails(): void
    {
        self::expectNotToPerformAssertions();

        $this->signedUrlGenerator
            ->method('generateResetPasswordUrl')
            ->willReturn('https://example.test/reset');
        $this->translator->method('trans')->willReturn('text');
        $this->mailer->method('send')->willThrowException(new \RuntimeException('fail'));

        $sender = new IdentityLinkEmailSender($this->signedUrlGenerator, $this->translator, $this->mailer);

        $sender->sendResetPasswordLink('user@example.com', 'fr', 'abc');
    }
}
