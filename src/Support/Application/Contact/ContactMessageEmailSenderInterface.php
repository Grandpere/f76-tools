<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

interface ContactMessageEmailSenderInterface
{
    public function send(string $email, string $subject, string $message, ?string $ip): void;
}
