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

namespace App\Support\Application\AdminUser;

use DateTimeImmutable;

final readonly class AdminUserListCriteria
{
    public function __construct(
        public string $googleFilter,
        public string $activeFilter,
        public string $roleFilter,
        public string $verifiedFilter,
        public string $localPasswordFilter,
        public ?DateTimeImmutable $createdFrom,
        public ?DateTimeImmutable $createdTo,
        public string $query,
        public string $sort,
        public string $dir,
        public int $page,
        public int $perPage,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
