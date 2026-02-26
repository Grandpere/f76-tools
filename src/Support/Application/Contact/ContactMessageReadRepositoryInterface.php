<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

use App\Domain\Support\Contact\ContactMessageStatusEnum;
use App\Entity\ContactMessageEntity;

interface ContactMessageReadRepositoryInterface
{
    /**
     * @return array{rows: list<ContactMessageEntity>, total: int}
     */
    public function findPaginated(string $query, ?ContactMessageStatusEnum $status, int $page, int $perPage): array;
}
