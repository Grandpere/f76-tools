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

namespace App\Identity\Infrastructure\Notification;

use App\Identity\Application\Notification\IdentityLinkEmailSenderInterface;
use App\Identity\Application\Notification\IdentitySignedLinkGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

final class IdentityLinkEmailSender implements IdentityLinkEmailSenderInterface
{
    public function __construct(
        private readonly IdentitySignedLinkGeneratorInterface $signedLinkGenerator,
        private readonly TranslatorInterface $translator,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function sendVerificationLink(string $email, string $locale, string $token): void
    {
        $verifyUrl = $this->signedLinkGenerator->generateVerificationUrl($locale, $token);

        $this->send(
            $email,
            (string) $this->translator->trans('security.verify.email_subject'),
            (string) $this->translator->trans('security.verify.email_intro'),
            $verifyUrl,
        );
    }

    public function sendResetPasswordLink(string $email, string $locale, string $token): void
    {
        $resetUrl = $this->signedLinkGenerator->generateResetPasswordUrl($locale, $token);

        $this->send(
            $email,
            (string) $this->translator->trans('security.forgot.email_subject'),
            (string) $this->translator->trans('security.forgot.email_intro'),
            $resetUrl,
        );
    }

    private function send(string $email, string $subject, string $intro, string $url): void
    {
        try {
            $this->mailer->send(
                new Email()
                    ->from('no-reply@f76.local')
                    ->to($email)
                    ->subject($subject)
                    ->text(sprintf("%s\n\n%s", $intro, $url)),
            );
        } catch (Throwable) {
            // Intentionally silent to preserve generic security responses.
        }
    }
}
