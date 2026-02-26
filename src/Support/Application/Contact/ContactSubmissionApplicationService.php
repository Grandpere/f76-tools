<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

final class ContactSubmissionApplicationService
{
    public function __construct(
        private readonly ContactMessageApplicationService $contactMessageApplicationService,
        private readonly ContactMessageEmailSenderInterface $contactMessageEmailSender,
    ) {
    }

    public function submit(ContactSubmissionInput $submissionInput, ?string $ip): ContactSubmissionStatus
    {
        try {
            $this->contactMessageApplicationService->createMessage(
                email: $submissionInput->email,
                subject: $submissionInput->subject,
                message: $submissionInput->message,
                ip: $ip,
            );
        } catch (\Throwable) {
            return ContactSubmissionStatus::PERSISTENCE_FAILED;
        }

        try {
            $this->contactMessageEmailSender->send(
                $submissionInput->email,
                $submissionInput->subject,
                $submissionInput->message,
                $ip,
            );
        } catch (\Throwable) {
            return ContactSubmissionStatus::SENT_WITH_DELIVERY_FAILURE;
        }

        return ContactSubmissionStatus::SENT;
    }
}
