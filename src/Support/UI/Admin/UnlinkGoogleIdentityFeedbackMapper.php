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

namespace App\Support\UI\Admin;

use App\Support\Application\AdminUser\UnlinkGoogleIdentityResult;

final class UnlinkGoogleIdentityFeedbackMapper
{
    /**
     * @return array{flashType: 'success'|'warning', flashMessage: string}
     */
    public function map(UnlinkGoogleIdentityResult $result): array
    {
        return match ($result) {
            UnlinkGoogleIdentityResult::USER_NOT_FOUND => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.user_not_found',
            ],
            UnlinkGoogleIdentityResult::GOOGLE_IDENTITY_NOT_FOUND => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.google_identity_not_found',
            ],
            UnlinkGoogleIdentityResult::LOCAL_PASSWORD_REQUIRED => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.google_identity_local_password_required',
            ],
            UnlinkGoogleIdentityResult::UNLINKED => [
                'flashType' => 'success',
                'flashMessage' => 'admin_users.flash.google_identity_unlinked',
            ],
        };
    }
}
