<?php

declare(strict_types=1);

namespace App\Support\Application\Contact;

use App\Domain\Support\Contact\ContactMessageStatusEnum;
use App\Entity\ContactMessageEntity;

final readonly class ContactMessageListResult
{
    /**
     * @param list<ContactMessageEntity> $rows
     */
    public function __construct(
        public array $rows,
        public int $totalRows,
        public string $query,
        public ?ContactMessageStatusEnum $status,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {
    }
}
