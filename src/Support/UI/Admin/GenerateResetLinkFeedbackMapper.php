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

use App\Support\Application\AdminUser\GenerateResetLinkResult;
use App\Support\Application\AdminUser\GenerateResetLinkStatus;
use DateTimeInterface;

final class GenerateResetLinkFeedbackMapper
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
    public function map(GenerateResetLinkResult $result): array
    {
        return match ($result->getStatus()) {
            GenerateResetLinkStatus::USER_NOT_FOUND => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.user_not_found',
                'flashParams' => [],
                'auditAction' => null,
                'auditContext' => null,
            ],
            GenerateResetLinkStatus::GLOBAL_RATE_LIMITED => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.reset_link_global_rate_limited',
                'flashParams' => [
                    '%seconds%' => (string) $result->getWindowSeconds(),
                    '%count%' => (string) $result->getMaxRequests(),
                ],
                'auditAction' => 'user_generate_reset_link_global_rate_limited',
                'auditContext' => [
                    'windowSeconds' => $result->getWindowSeconds(),
                    'maxRequests' => $result->getMaxRequests(),
                ],
            ],
            GenerateResetLinkStatus::COOLDOWN_RATE_LIMITED => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.reset_link_rate_limited',
                'flashParams' => [
                    '%seconds%' => (string) $result->getRemainingSeconds(),
                ],
                'auditAction' => 'user_generate_reset_link_rate_limited',
                'auditContext' => [
                    'remainingSeconds' => $result->getRemainingSeconds(),
                ],
            ],
            GenerateResetLinkStatus::GENERATED => [
                'flashType' => 'success',
                'flashMessage' => 'admin_users.flash.reset_link_generated',
                'flashParams' => [],
                'auditAction' => 'user_generate_reset_link',
                'auditContext' => [
                    'expiresAt' => $result->getExpiresAt()?->format(DateTimeInterface::ATOM),
                ],
            ],
        };
    }
}
