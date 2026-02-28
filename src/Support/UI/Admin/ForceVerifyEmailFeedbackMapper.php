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

use App\Support\Application\AdminUser\ForceVerifyEmailResult;

final class ForceVerifyEmailFeedbackMapper
{
    /**
     * @return array{
     *     flashType: 'success'|'warning',
     *     flashMessage: string,
     *     auditAction: string|null
     * }
     */
    public function map(ForceVerifyEmailResult $result): array
    {
        return match ($result) {
            ForceVerifyEmailResult::USER_NOT_FOUND => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.user_not_found',
                'auditAction' => null,
            ],
            ForceVerifyEmailResult::ALREADY_VERIFIED => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.verification_email_already_verified',
                'auditAction' => 'user_force_verify_email_already_verified',
            ],
            ForceVerifyEmailResult::VERIFIED => [
                'flashType' => 'success',
                'flashMessage' => 'admin_users.flash.verification_email_forced',
                'auditAction' => 'user_force_verify_email',
            ],
        };
    }
}
