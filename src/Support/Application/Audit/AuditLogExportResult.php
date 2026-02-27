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

namespace App\Support\Application\Audit;

use App\Entity\AdminAuditLogEntity;

final readonly class AuditLogExportResult
{
    /**
     * @param list<AdminAuditLogEntity> $rows
     */
    public function __construct(
        public array $rows,
        public string $query,
        public string $action,
    ) {
    }
}
