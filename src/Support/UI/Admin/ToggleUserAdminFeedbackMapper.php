<?php

declare(strict_types=1);

namespace App\Support\UI\Admin;

use App\Support\Application\AdminUser\ToggleUserAdminResult;

final class ToggleUserAdminFeedbackMapper
{
    /**
     * @return array{flashType: 'success'|'warning', flashMessage: string}
     */
    public function map(ToggleUserAdminResult $result): array
    {
        return match ($result) {
            ToggleUserAdminResult::ACTOR_REQUIRED => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.user_not_found',
            ],
            ToggleUserAdminResult::USER_NOT_FOUND => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.user_not_found',
            ],
            ToggleUserAdminResult::CANNOT_CHANGE_SELF => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.cannot_change_self_role',
            ],
            ToggleUserAdminResult::UPDATED => [
                'flashType' => 'success',
                'flashMessage' => 'admin_users.flash.role_updated',
            ],
        };
    }
}

