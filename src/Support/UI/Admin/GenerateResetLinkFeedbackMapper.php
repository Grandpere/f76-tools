<?php

declare(strict_types=1);

namespace App\Support\UI\Admin;

use App\Support\Application\AdminUser\GenerateResetLinkResult;
use App\Support\Application\AdminUser\GenerateResetLinkStatus;

final class GenerateResetLinkFeedbackMapper
{
    /**
     * @return array{
     *     flashType: 'success'|'warning',
     *     flashMessage: string,
     *     flashParams: array<string, string>,
     *     auditAction: string|null,
     *     auditContext: array<string, mixed>|null
     * }
     */
    public function map(GenerateResetLinkResult $result): array
    {
        return match ($result->getStatus()) {
            GenerateResetLinkStatus::ACTOR_REQUIRED => [
                'flashType' => 'warning',
                'flashMessage' => 'admin_users.flash.user_not_found',
                'flashParams' => [],
                'auditAction' => null,
                'auditContext' => null,
            ],
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
                    'expiresAt' => $result->getExpiresAt()?->format(\DateTimeInterface::ATOM),
                ],
            ],
        };
    }
}

