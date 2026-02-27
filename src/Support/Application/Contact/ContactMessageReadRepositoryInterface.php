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

use App\Domain\Support\Contact\ContactMessageStatusEnum;
use App\Entity\ContactMessageEntity;

interface ContactMessageReadRepositoryInterface
{
    /**
     * @return array{rows: list<ContactMessageEntity>, total: int}
     */
    public function findPaginated(string $query, ?ContactMessageStatusEnum $status, int $page, int $perPage): array;
}
