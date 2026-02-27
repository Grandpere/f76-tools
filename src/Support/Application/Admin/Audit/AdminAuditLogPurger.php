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

namespace App\Support\Application\Admin\Audit;

use DateTimeImmutable;

interface AdminAuditLogPurger
{
    public function countOlderThan(DateTimeImmutable $cutoff): int;

    public function deleteOlderThan(DateTimeImmutable $cutoff): int;
}
