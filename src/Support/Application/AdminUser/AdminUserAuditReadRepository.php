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

use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;

interface AdminUserAuditReadRepository
{
    /**
     * @param list<string> $actions
     */
    public function countRecentActionsByActor(UserEntity $actor, array $actions, DateTimeImmutable $since): int;
}
