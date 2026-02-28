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

use App\Support\Application\AdminUser\ResendVerificationEmailResult;
use App\Support\Application\AdminUser\ResendVerificationEmailStatus;

final class ResendVerificationEmailFeedbackMapper
{
    /**
     * @return array{
     *     flashType: 'success'|'warning',
     *     flashMessage: string,
     *     flashParams: array<string, string>,
     *     auditAction: string|null,
     *     auditContext: array<string, bool|int|string|null>|null
     * }
     */
    public function map(ResendVerificationEmailResult $result): array
    {
        return match ($result->status()) {
            ResendVerificationEmailStatus::USER_NOT_FOUND => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.user_not_found',
                'flashParams' => [],
                'auditAction' => null,
                'auditContext' => null,
            ],
            ResendVerificationEmailStatus::ALREADY_VERIFIED => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.verification_email_already_verified',
                'flashParams' => [],
                'auditAction' => 'user_resend_verification_already_verified',
                'auditContext' => null,
            ],
            ResendVerificationEmailStatus::RATE_LIMITED => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.verification_email_rate_limited',
                'flashParams' => [
                    '%seconds%' => (string) $result->remainingSeconds(),
                ],
                'auditAction' => 'user_resend_verification_rate_limited',
                'auditContext' => [
                    'remainingSeconds' => $result->remainingSeconds(),
                ],
            ],
            ResendVerificationEmailStatus::GENERATED => [
                'flashType' => 'success',
                'flashMessage' => 'admin_users.flash.verification_email_sent',
                'flashParams' => [],
                'auditAction' => 'user_resend_verification_email',
                'auditContext' => null,
            ],
        };
    }
}
