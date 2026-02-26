<?php

declare(strict_types=1);

namespace App\Support\Application\AdminUser;

use App\Entity\UserEntity;
use DateTimeImmutable;

interface AdminUserAuditReadRepositoryInterface
{
    /**
     * @param list<string> $actions
     */
    public function countRecentActionsByActor(UserEntity $actor, array $actions, DateTimeImmutable $since): int;
}
