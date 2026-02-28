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

namespace App\Support\Infrastructure\Contact;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ContactMessageEmailSender implements \App\Support\Application\Contact\ContactMessageEmailSender
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $contactRecipientEmail,
    ) {
    }

    public function send(string $email, string $subject, string $message, ?string $ip): void
    {
        $this->mailer->send(
            new Email()
                ->from('no-reply@f76.local')
                ->to($this->contactRecipientEmail)
                ->replyTo($email)
                ->subject(sprintf('[F76 Contact] %s', $subject))
                ->text(sprintf("From: %s\nIP: %s\n\n%s", $email, $ip ?? 'unknown', $message)),
        );
    }
}
