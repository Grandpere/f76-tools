<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

use App\Entity\ContactMessageEntity;

interface ContactMessageStatusWriteRepositoryInterface
{
    public function getById(int $id): ?ContactMessageEntity;

    public function save(ContactMessageEntity $message): void;
}
