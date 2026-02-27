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

final class ContactMessageListApplicationService
{
    public function __construct(
        private readonly ContactMessageReadRepositoryInterface $contactMessageRepository,
    ) {
    }

    public function list(ContactMessageListQuery $query): ContactMessageListResult
    {
        $result = $this->contactMessageRepository->findPaginated($query->query, $query->status, $query->page, $query->perPage);
        $totalRows = $result['total'];
        $totalPages = max(1, (int) ceil($totalRows / $query->perPage));
        $page = min($query->page, $totalPages);
        if ($page !== $query->page) {
            $result = $this->contactMessageRepository->findPaginated($query->query, $query->status, $page, $query->perPage);
        }

        return new ContactMessageListResult(
            rows: $result['rows'],
            totalRows: $totalRows,
            query: $query->query,
            status: $query->status,
            page: $page,
            perPage: $query->perPage,
            totalPages: $totalPages,
        );
    }
}
