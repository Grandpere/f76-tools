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

use App\Contract\ContactMessageWriterInterface;
use App\Entity\ContactMessageEntity;

final class ContactMessageApplicationService
{
    public function __construct(
        private readonly ContactMessageWriterInterface $messageWriter,
    ) {
    }

    public function createMessage(string $email, string $subject, string $message, ?string $ip): ContactMessageEntity
    {
        $contactMessage = (new ContactMessageEntity())
            ->setEmail($email)
            ->setSubject($subject)
            ->setMessage($message)
            ->setIp($ip);

        $this->messageWriter->save($contactMessage);

        return $contactMessage;
    }
}
