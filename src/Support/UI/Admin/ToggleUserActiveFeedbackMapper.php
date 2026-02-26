<?php

declare(strict_types=1);

namespace App\Support\UI\Admin;

use App\Support\Application\AdminUser\ToggleUserActiveResult;

final class ToggleUserActiveFeedbackMapper
{
    /**
     * @return array{flashType: 'success'|'warning', flashMessage: string}
     */
    public function map(ToggleUserActiveResult $result): array
    {
        return match ($result) {
            ToggleUserActiveResult::ACTOR_REQUIRED => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.user_not_found',
            ],
            ToggleUserActiveResult::USER_NOT_FOUND => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.user_not_found',
            ],
            ToggleUserActiveResult::CANNOT_CHANGE_SELF => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.cannot_change_self_active',
            ],
            ToggleUserActiveResult::UPDATED => [
                'flashType' => 'success',
                'flashMessage' => 'admin_users.flash.active_updated',
            ],
        };
    }
}
