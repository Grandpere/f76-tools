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

use App\Support\Domain\Contact\ContactMessageStatusEnum;
use App\Support\Domain\Entity\ContactMessageEntity;

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
