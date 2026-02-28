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

namespace App\Support\Application\Contact;

use Throwable;

final class ContactSubmissionApplicationService
{
    public function __construct(
        private readonly ContactMessageApplicationService $contactMessageApplicationService,
        private readonly ContactMessageEmailSender $contactMessageEmailSender,
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
        } catch (Throwable) {
            return ContactSubmissionStatus::PERSISTENCE_FAILED;
        }

        try {
            $this->contactMessageEmailSender->send(
                $submissionInput->email,
                $submissionInput->subject,
                $submissionInput->message,
                $ip,
            );
        } catch (Throwable) {
            return ContactSubmissionStatus::SENT_WITH_DELIVERY_FAILURE;
        }

        return ContactSubmissionStatus::SENT;
    }
}
