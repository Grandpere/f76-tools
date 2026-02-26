<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

final readonly class ContactSubmissionInput
{
    private const MIN_SUBJECT_LENGTH = 3;
    private const MIN_MESSAGE_LENGTH = 10;

    private function __construct(
        public string $email,
        public string $subject,
        public string $message,
    ) {
    }

    public static function create(string $email, string $subject, string $message): self
    {
        return new self(
            mb_strtolower(trim($email)),
            trim($subject),
            trim($message),
        );
    }

    public function isValid(): bool
    {
        return (bool) filter_var($this->email, FILTER_VALIDATE_EMAIL)
            && mb_strlen($this->subject) >= self::MIN_SUBJECT_LENGTH
            && mb_strlen($this->message) >= self::MIN_MESSAGE_LENGTH;
    }
}
