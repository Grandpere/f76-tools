<?php

declare(strict_types=1);

namespace App\Support\Infrastructure\Contact;

use App\Support\Application\Contact\ContactMessageEmailSenderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ContactMessageEmailSender implements ContactMessageEmailSenderInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $contactRecipientEmail,
    ) {
    }

    public function send(string $email, string $subject, string $message, ?string $ip): void
    {
        $this->mailer->send(
            (new Email())
                ->from('no-reply@f76.local')
                ->to($this->contactRecipientEmail)
                ->replyTo($email)
                ->subject(sprintf('[F76 Contact] %s', $subject))
                ->text(sprintf("From: %s\nIP: %s\n\n%s", $email, $ip ?? 'unknown', $message)),
        );
    }
}
